<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:abas-test-connection',
    description: 'Test the ABAS ERP middleware connection by probing endpoints and optionally sending a test inquiry',
)]
class AbbasTestConnectionCommand extends Command
{
    // Candidate POST paths to probe (derived from ABAS doc + common API patterns)
    private const POST_CANDIDATES = [
        '/cupo/inq0',
        '/inq0',
        '/inq',
        '/Inq',
        '/api/inq',
        '/api/inquiry',
        '/inquiry',
        '/',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $abasInterfaceUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'Override ABAS middleware base URL (defaults to ABAS_INTERFACE_URL env)')
            ->addOption('send-test', null, InputOption::VALUE_NONE, 'Send a test NewInquiryRequest to the discovered POST endpoint')
            ->addOption('probe', null, InputOption::VALUE_NONE, 'Probe multiple candidate paths to discover the correct POST endpoint')
            ->addOption('customer-id', null, InputOption::VALUE_OPTIONAL, 'Customer ID for the test inquiry', '400000')
            ->addOption('division', null, InputOption::VALUE_OPTIONAL, 'Division for the test inquiry (TX or RC)', 'TX')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseUrl = rtrim($input->getOption('url') ?? $this->abasInterfaceUrl, '/');

        $io->title('ABAS Middleware Connection Test');
        $io->text(sprintf('Base URL: <info>%s</info>', $baseUrl));
        $io->newLine();

        // Step 1: Test connectivity + identify server
        $io->section('1. Testing connectivity');
        $serverName = null;
        try {
            $response = $this->httpClient->request('GET', $baseUrl, [
                'timeout' => 10,
                'verify_peer' => false,
                'verify_host' => false,
            ]);
            $statusCode = $response->getStatusCode();
            $rootContent = $response->getContent(false);
            $io->text(sprintf('GET %s -> <info>%d</info>', $baseUrl, $statusCode));

            if (!empty($rootContent)) {
                $decoded = json_decode($rootContent, true);
                if ($decoded !== null) {
                    $io->text('Response: ' . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $serverName = $decoded['message'] ?? null;
                } else {
                    $io->text('Response: ' . $this->truncate($rootContent, 300));
                }
            }

            $io->text('<fg=green>Connection successful.</>');
        } catch (\Exception $e) {
            $io->error(sprintf('Connection failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        // Step 2: Probe for POST endpoint
        if ($input->getOption('probe') || $input->getOption('send-test')) {
            $io->section('2. Probing POST endpoints');
            $io->text('Sending empty JSON {} to discover which path accepts POST...');
            $io->newLine();

            $foundEndpoint = null;
            $probeResults = [];

            foreach (self::POST_CANDIDATES as $path) {
                $url = $baseUrl . ($path === '/' ? '' : $path);
                try {
                    $response = $this->httpClient->request('POST', $url, [
                        'timeout' => 10,
                        'verify_peer' => false,
                        'verify_host' => false,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                        'body' => '{}',
                    ]);

                    $code = $response->getStatusCode();
                    $body = $response->getContent(false);
                    $isJson = json_decode($body, true) !== null;
                    $is404Html = str_contains($body, 'Cannot POST');

                    if ($is404Html) {
                        $io->text(sprintf('  POST %-20s -> <fg=red>%d</> (Cannot POST)', $path, $code));
                        $probeResults[$path] = 'not found';
                    } elseif ($code !== 404) {
                        $io->text(sprintf('  POST %-20s -> <fg=green>%d</> %s', $path, $code, $isJson ? '(JSON response)' : ''));
                        if ($foundEndpoint === null) {
                            $foundEndpoint = $path;
                        }
                        $probeResults[$path] = $code;
                        if ($isJson && !empty($body)) {
                            $io->text('       Response: ' . $this->truncate($body, 200));
                        }
                    } else {
                        $io->text(sprintf('  POST %-20s -> <fg=red>%d</>', $path, $code));
                        $probeResults[$path] = 'not found';
                    }
                } catch (\Exception $e) {
                    $io->text(sprintf('  POST %-20s -> <fg=red>ERROR</> %s', $path, $this->truncate($e->getMessage(), 80)));
                    $probeResults[$path] = 'error';
                }
            }

            if ($foundEndpoint !== null) {
                $io->newLine();
                $io->text(sprintf('<fg=green>Found POST endpoint:</> <info>%s</info>', $foundEndpoint));
            } else {
                $io->newLine();
                $io->warning('No POST endpoint found. The middleware may not be fully configured yet, or uses a different path. Ask grafit.pas for the correct endpoint.');
            }
        }

        // Step 3: Send real test inquiry to discovered endpoint
        if ($input->getOption('send-test') && $foundEndpoint !== null) {
            $io->section('3. Sending test NewInquiryRequest');

            $portalRefID = 'Inq-' . $input->getOption('customer-id') . '-' . date('y') . '-' . time();
            $customerId = $input->getOption('customer-id');
            $division = $input->getOption('division');

            $inquiryRequest = [
                'headData' => [
                    'customerID' => $customerId,
                    'division' => $division,
                    'SBShort' => '',
                    'SBMail' => '',
                    'TSShort' => '',
                    'TSMail' => '',
                    'AMShort' => '',
                    'AMMail' => '',
                    'portalRefID' => $portalRefID,
                    'inquiryType' => 'NEW',
                    'userBackLink' => 'https://kde.staco.at/inq/' . $portalRefID,
                ],
                'articles' => [
                    [
                        'articleNbr' => 'Z4R-10463A',
                        'portArtRefID' => '1',
                        'pcs' => 1.0,
                    ],
                ],
            ];

            $jsonContent = json_encode($inquiryRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $postUrl = $baseUrl . ($foundEndpoint === '/' ? '' : $foundEndpoint);

            $io->text(sprintf('Endpoint: <info>POST %s</info>', $foundEndpoint));
            $io->text(sprintf('Portal Ref ID: <info>%s</info>', $portalRefID));
            $io->text('Request payload:');
            $io->text($jsonContent);
            $io->newLine();

            try {
                $response = $this->httpClient->request('POST', $postUrl, [
                    'timeout' => 30,
                    'verify_peer' => false,
                    'verify_host' => false,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => $jsonContent,
                ]);

                $postStatusCode = $response->getStatusCode();
                $responseBody = $response->getContent(false);

                $io->text(sprintf('POST %s -> <info>%d</info>', $postUrl, $postStatusCode));

                if (!empty($responseBody)) {
                    $io->newLine();
                    $io->text('Response body:');
                    $decoded = json_decode($responseBody, true);
                    if ($decoded !== null) {
                        $io->text(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        $this->analyzeResponse($io, $decoded);
                    } else {
                        $io->text($this->truncate($responseBody, 2000));
                    }
                }

                if ($postStatusCode >= 200 && $postStatusCode < 300) {
                    $io->success(sprintf('Test inquiry sent successfully (HTTP %d).', $postStatusCode));
                } elseif ($postStatusCode >= 400 && $postStatusCode < 500) {
                    $io->warning(sprintf('Middleware returned client error (HTTP %d).', $postStatusCode));
                } elseif ($postStatusCode >= 500) {
                    $io->error(sprintf('Middleware returned server error (HTTP %d).', $postStatusCode));
                }
            } catch (\Exception $e) {
                $io->error(sprintf('POST request failed: %s', $e->getMessage()));
                return Command::FAILURE;
            }
        } elseif ($input->getOption('send-test') && $foundEndpoint === null) {
            $io->warning('Skipping test inquiry - no POST endpoint was discovered.');
        }

        // Summary
        $io->newLine();
        $io->section('Summary');
        $rows = [
            ['Middleware', $serverName ? trim($serverName) : 'Unknown'],
            ['Connectivity', sprintf('<fg=green>HTTP %d</>', $statusCode)],
        ];
        if (isset($foundEndpoint)) {
            $rows[] = ['POST endpoint', $foundEndpoint ?? '<fg=red>Not found</>'];
        }
        if (isset($postStatusCode)) {
            $rows[] = ['Test inquiry', sprintf('HTTP %d', $postStatusCode)];
        }
        $io->table(['Check', 'Result'], $rows);

        $io->success('ABAS middleware connection test completed.');

        return Command::SUCCESS;
    }

    private function analyzeResponse(SymfonyStyle $io, array $decoded): void
    {
        $io->newLine();

        if (isset($decoded['type'])) {
            $io->text(sprintf('Response type: <info>%s</info>', $decoded['type']));
        }

        if (isset($decoded['error'])) {
            if ($decoded['error'] === false) {
                $io->text('<fg=green>Middleware/ABAS reports no error.</>');
            } else {
                $io->text(sprintf(
                    '<fg=red>Middleware/ABAS reports error: [%s] %s</>',
                    $decoded['errorCode'] ?? 'unknown',
                    $decoded['errorMsg'] ?? 'no message'
                ));
            }
        }

        if (isset($decoded['ABASRefID'])) {
            $io->text(sprintf('ABAS Ref ID: <info>%s</info>', $decoded['ABASRefID']));
        }

        if (isset($decoded['portalRefID'])) {
            $io->text(sprintf('Portal Ref ID: <info>%s</info>', $decoded['portalRefID']));
        }
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '... (truncated)';
    }
}
