<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\InquiryMachine;
use App\Entity\InquiryMachinePart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\AreaAssignmentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AbasInterfaceService
{
    // Middleware endpoint paths (relative to base URL)
    private const ENDPOINT_INQUIRY = '/inq0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly AreaAssignmentRepository $areaAssignmentRepository,
        private readonly string $abasInterfaceUrl,
        private readonly string $clientAppUrl,
    ) {
    }

    // ─── SENDING REQUESTS ───────────────────────────────────────────────

    /**
     * Fall 0: Send a new inquiry to ABAS via middleware
     *
     * Spec: Fall0/NewInquiryRequestObject.json
     * Path: ${ABASInterfaceRoot}/Inq/${YYYYMMDD.hhmmss}-${portalRefID}.json
     */
    public function sendNewInquiry(Inquiry $inquiry): array
    {
        $payload = $this->buildInquiryPayload($inquiry, 'NEW');

        $this->logger->info('Sending new inquiry to ABAS (Fall 0)', [
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'inquiry_number' => $inquiry->getInquiryNumber(),
            'customer_id' => $payload['headData']['customerID'] ?? null,
        ]);

        return $this->sendRequest(self::ENDPOINT_INQUIRY, $payload);
    }

    /**
     * Fall 2: Send an updated inquiry to ABAS via middleware (retrigger)
     *
     * Spec: Fall2/UpdateInquiryRequestObject.json
     * Path: ${ABASInterfaceRoot}/Inq/${YYYYMMDD.hhmmss}-${portalRefID}.json
     *
     * @param array $abasContext Keyed by portArtRefID (sequential string "1","2",...).
     *   Each value: ['ABASRowRefID'=>'', 'price'=>0, 'ABASOfferID'=>'', 'ABASRefID'=>'']
     */
    public function sendUpdateInquiry(Inquiry $inquiry, array $abasContext = []): array
    {
        $payload = $this->buildInquiryPayload($inquiry, 'UPDATE', $abasContext);

        $this->logger->info('Sending update inquiry to ABAS (Fall 2)', [
            'inquiry_id' => $inquiry->getId()->toRfc4122(),
            'inquiry_number' => $inquiry->getInquiryNumber(),
            'customer_id' => $payload['headData']['customerID'] ?? null,
        ]);

        return $this->sendRequest(self::ENDPOINT_INQUIRY, $payload);
    }

    /**
     * Fall 1: Send an order to ABAS via middleware (no retrigger, direct order)
     *
     * Spec: Fall1/OrderRequestObj.json
     * Path: ${ABASInterfaceRoot}/Order/${YYYYMMDD.hhmmss}-${portalRefID}.json
     *
     * @param array $offerArticles Articles from the offer response (articlesSuccess),
     *   each must include: articleNbr, portArtRefID, pcs, unit, name, pos, price, ABASOfferNbr, ABASRefNbr
     */
    public function sendOrder(Order $order, string $portalRefID, array $offerArticles): array
    {
        $user = $order->getUser();
        $client = $user?->getClient();
        $customerID = $client?->getCode() ?? '';

        $payload = [
            'headData' => [
                'customerID' => $customerID,
                'portalRefID' => $portalRefID,
                'orderRefNbr' => $order->getOrderNumber(),
            ],
            'articlesSelected' => $this->buildArticlesForOrder($offerArticles),
        ];

        $this->logger->info('Sending order to ABAS (Fall 1)', [
            'order_id' => $order->getId()->toRfc4122(),
            'order_number' => $order->getOrderNumber(),
            'portal_ref_id' => $portalRefID,
            'customer_id' => $customerID,
            'articles_count' => count($payload['articlesSelected']),
        ]);

        return $this->sendRequest(self::ENDPOINT_INQUIRY, $payload);
    }

    // ─── PARSING RESPONSES ──────────────────────────────────────────────

    /**
     * Parse a new inquiry response from ABAS (Fall 0)
     *
     * Spec: Fall0/NewInquiryResponseObject.json
     * Type: "NewInquiryResponse"
     * Fields: type, error, errorCode, errorMsg, portalRefID, ABASRefID
     * Note: NO customerID in Fall 0 response
     */
    public function parseNewInquiryResponse(array $response): array
    {
        return [
            'type' => $response['type'] ?? '',
            'success' => !($response['error'] ?? true),
            'errorCode' => $response['errorCode'] ?? '',
            'errorMsg' => $response['errorMsg'] ?? '',
            'portalRefID' => $response['portalRefID'] ?? '',
            'abasRefID' => $response['ABASRefID'] ?? '',
        ];
    }

    /**
     * Parse an update inquiry response from ABAS (Fall 2)
     *
     * Spec: Fall2/InquiryResponseObject.json
     * Type: "UpdateInquiryResponse"
     * Fields: type, error, errorCode, errorMsg, portalRefID, customerID, ABASRefID
     * Note: HAS customerID (unlike Fall 0)
     */
    public function parseUpdateInquiryResponse(array $response): array
    {
        return [
            'type' => $response['type'] ?? '',
            'success' => !($response['error'] ?? true),
            'errorCode' => $response['errorCode'] ?? '',
            'errorMsg' => $response['errorMsg'] ?? '',
            'portalRefID' => $response['portalRefID'] ?? '',
            'customerID' => $response['customerID'] ?? '',
            'abasRefID' => $response['ABASRefID'] ?? '',
        ];
    }

    /**
     * Parse an offer response from ABAS (Fall 0 offer)
     *
     * Spec: Fall0/OfferResponseObjekt.json
     * Fields: headData.ABASRefID, headData.ABASOfferID (note: "ID" suffix)
     * Articles have: articleNbr, portArtRefID, pcs, unit, name, ABASRowRefID, pos, price, discount
     * articleNbr can be: ArticleNr | AREPL | TEXT | ALOSEBGR
     * Dummy articles may have no portArtRefID
     */
    public function parseOfferResponseFall0(array $response): array
    {
        $headData = $response['headData'] ?? [];

        return [
            'customerID' => $headData['customerID'] ?? '',
            'portalRefID' => $headData['portalRefID'] ?? '',
            'abasRefID' => $headData['ABASRefID'] ?? '',
            'abasOfferID' => $headData['ABASOfferID'] ?? '',
            'infoText' => $headData['infoText'] ?? '',
            'articles' => $response['articlesSuccess'] ?? [],
        ];
    }

    /**
     * Parse an offer response from ABAS (Fall 2 offer)
     *
     * Spec: Fall2/OfferResponseObjekt.json
     * Fields: headData.ABASRefNbr, headData.ABASOfferNbr (note: "Nbr" suffix)
     * Articles have: articleNbr, portArtRefID, pcs, unit, name, ABASRowRefID, pos, price, discount
     */
    public function parseOfferResponseFall2(array $response): array
    {
        $headData = $response['headData'] ?? [];

        return [
            'customerID' => $headData['customerID'] ?? '',
            'portalRefID' => $headData['portalRefID'] ?? '',
            'abasRefNbr' => $headData['ABASRefNbr'] ?? '',
            'abasOfferNbr' => $headData['ABASOfferNbr'] ?? '',
            'infoText' => $headData['infoText'] ?? '',
            'articles' => $response['articlesSuccess'] ?? [],
        ];
    }

    /**
     * Parse an offer response from ABAS (Fall 1 — before order)
     *
     * Spec: Fall1/OfferResponseObject.json
     * Type: "OfferResponse"
     * Fields: type, error, errorCode, errorMsg, portalRefID, customerID, ABASRefID
     */
    public function parseOfferResponseFall1(array $response): array
    {
        return [
            'type' => $response['type'] ?? '',
            'success' => !($response['error'] ?? true),
            'errorCode' => $response['errorCode'] ?? '',
            'errorMsg' => $response['errorMsg'] ?? '',
            'portalRefID' => $response['portalRefID'] ?? '',
            'customerID' => $response['customerID'] ?? '',
            'abasRefID' => $response['ABASRefID'] ?? '',
        ];
    }

    /**
     * Parse an order confirmation response from ABAS (Fall 1)
     *
     * Spec: Fall1/OrderConfirmationResponseObj.json
     * Path: ${ABASInterfaceRoot}/OrderRes/${YYYYMMDD.hhmmss}-${portalRefID}.json
     */
    public function parseOrderConfirmation(array $response): array
    {
        return [
            'customerID' => $response['customerID'] ?? '',
            'portalRefID' => $response['portalRefID'] ?? '',
            'orderRefID' => $response['orderRefID'] ?? '',
            'abasOrderID' => $response['ABASOrderID'] ?? '',
            'abasOrderPDFPath' => $response['ABASOrderPDFPath'] ?? '',
        ];
    }

    // ─── PAYLOAD BUILDERS ───────────────────────────────────────────────

    /**
     * Build the full inquiry payload for ABAS (Fall 0 + Fall 2)
     */
    private function buildInquiryPayload(Inquiry $inquiry, string $inquiryType, array $abasContext = []): array
    {
        $headData = $this->resolveInquiryHeadData($inquiry, $inquiryType);
        $articles = $this->buildArticlesFromInquiry($inquiry, $inquiryType, $abasContext);

        return [
            'headData' => $headData,
            'articles' => $articles,
        ];
    }

    /**
     * Resolve headData fields from inquiry and area assignment
     *
     * Spec fields: customerID, division, SBShort, SBMail, TSShort, TSMail,
     *              AMShort, AMMail, portalRefID, inquiryType, userBackLink
     */
    private function resolveInquiryHeadData(Inquiry $inquiry, string $inquiryType): array
    {
        $user = $inquiry->getUser();
        $client = $user?->getClient();
        $customerID = $client?->getCode() ?? '';

        // Resolve SB/TS/AM from area assignment
        $staffInfo = $this->resolveStaffFromAssignment($inquiry);

        // Build back-link URL: "https://kde.staco.at/inq/${portalRefID}"
        $userBackLink = rtrim($this->clientAppUrl, '/') . '/inq/' . $inquiry->getInquiryNumber();

        return [
            'customerID' => $customerID,
            'division' => '', // TX | RC — selected by customer during inquiry creation
            'SBShort' => $staffInfo['SBShort'],
            'SBMail' => $staffInfo['SBMail'],
            'TSShort' => $staffInfo['TSShort'],
            'TSMail' => $staffInfo['TSMail'],
            'AMShort' => $staffInfo['AMShort'],
            'AMMail' => $staffInfo['AMMail'],
            'portalRefID' => $inquiry->getInquiryNumber(),
            'inquiryType' => $inquiryType, // "NEW" | "UPDATE"
            'userBackLink' => $userBackLink,
        ];
    }

    /**
     * Resolve staff contacts (SB, TS, AM) from the inquiry's area assignment
     *
     * AreaManager.specializations JSON can contain role info (e.g. ["SB"], ["TS"], ["AM"]).
     * AreaManager.metadata JSON can contain {"abasShort": "XX"} for the Kürzel.
     */
    private function resolveStaffFromAssignment(Inquiry $inquiry): array
    {
        $result = [
            'SBShort' => '',
            'SBMail' => '',
            'TSShort' => '',
            'TSMail' => '',
            'AMShort' => '',
            'AMMail' => '',
        ];

        $assignment = $this->areaAssignmentRepository->findActiveByInquiry($inquiry);
        if ($assignment === null) {
            $this->logger->warning('No active area assignment found for inquiry', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
            ]);
            return $result;
        }

        $area = $assignment->getAreaManager()->getArea();
        if ($area === null) {
            return $result;
        }

        // Iterate area managers and map by specialization
        foreach ($area->getActiveManagers() as $areaManager) {
            $specializations = $areaManager->getSpecializations() ?? [];
            $metadata = $areaManager->getMetadata() ?? [];
            $manager = $areaManager->getManager();
            $short = $metadata['abasShort'] ?? '';
            $email = $manager?->getEmail() ?? '';

            if (in_array('SB', $specializations, true)) {
                $result['SBShort'] = $short;
                $result['SBMail'] = $email;
            }
            if (in_array('TS', $specializations, true)) {
                $result['TSShort'] = $short;
                $result['TSMail'] = $email;
            }
            if (in_array('AM', $specializations, true)) {
                $result['AMShort'] = $short;
                $result['AMMail'] = $email;
            }
        }

        return $result;
    }

    /**
     * Build the articles array from inquiry machines/parts
     *
     * Fall 0 (NEW): articleNbr, portArtRefID (sequential "1","2",...), pcs
     * Fall 2 (UPDATE): + ABASRowRefID, pos, price, ABASOfferID, ABASRefID
     *
     * portArtRefID: "fortlaufend nummeriert" (sequentially numbered), max 255 chars.
     *   Used as reference when green articles are sent back to the portal.
     *   For UPDATE: "IMMER NEUE ID" (always new ID) — always regenerated.
     */
    private function buildArticlesFromInquiry(Inquiry $inquiry, string $inquiryType, array $abasContext = []): array
    {
        $articles = [];
        $counter = 1; // portArtRefID is sequential: "1", "2", "3", ...

        /** @var InquiryMachine $machine */
        foreach ($inquiry->getMachines() as $machine) {
            /** @var InquiryMachinePart $part */
            foreach ($machine->getProducts() as $part) {
                $portArtRefID = (string) $counter;

                // Look up previous ABAS references for this part (for UPDATE type)
                // abasContext can be keyed by part UUID for internal lookup
                $partId = $part->getId()->toRfc4122();
                $ctx = $abasContext[$partId] ?? [];

                $article = [
                    'articleNbr' => $part->getPartNumber() ?? '',
                    'portArtRefID' => $portArtRefID,
                    'pcs' => 1.0, // InquiryMachinePart has no quantity field — defaults to 1
                ];

                // Fall 2 (UPDATE) includes additional ABAS reference fields
                if ($inquiryType === 'UPDATE') {
                    $article['ABASRowRefID'] = $ctx['ABASRowRefID'] ?? '';
                    $article['pos'] = $counter;
                    $article['price'] = $ctx['price'] ?? 0;
                    $article['ABASOfferID'] = $ctx['ABASOfferID'] ?? '';
                    $article['ABASRefID'] = $ctx['ABASRefID'] ?? '';
                }

                $articles[] = $article;
                $counter++;
            }
        }

        return $articles;
    }

    /**
     * Build articlesSelected for order request (Fall 1)
     *
     * Spec: Fall1/OrderRequestObj.json
     * Fields: articleNbr, portArtRefID, pcs, unit, name, pos, price, ABASOfferNbr, ABASRefNbr
     * Note: uses "Nbr" suffix (ABASOfferNbr, ABASRefNbr), NOT "ID" suffix
     *
     * @param array $offerArticles Articles from the offer articlesSuccess response
     */
    private function buildArticlesForOrder(array $offerArticles): array
    {
        $selected = [];

        foreach ($offerArticles as $article) {
            // Skip TEXT, AREPL, and dummy articles (no real product)
            $articleNbr = $article['articleNbr'] ?? '';
            if (in_array($articleNbr, ['TEXT', 'AREPL'], true)) {
                continue;
            }

            $selected[] = [
                'articleNbr' => $articleNbr,
                'portArtRefID' => $article['portArtRefID'] ?? '',
                'pcs' => $article['pcs'] ?? 0,
                'unit' => $article['unit'] ?? '',
                'name' => $article['name'] ?? '',
                'pos' => $article['pos'] ?? 0,
                'price' => $article['price'] ?? 0,
                'ABASOfferNbr' => $article['ABASOfferNbr'] ?? $article['ABASOfferID'] ?? '',
                'ABASRefNbr' => $article['ABASRefNbr'] ?? $article['ABASRefID'] ?? '',
            ];
        }

        return $selected;
    }

    // ─── HTTP TRANSPORT ─────────────────────────────────────────────────

    /**
     * Send HTTP POST request to the ABAS middleware
     *
     * @return array{success: bool, statusCode: int, data: array, error: string}
     */
    private function sendRequest(string $endpoint, array $payload): array
    {
        $url = rtrim($this->abasInterfaceUrl, '/') . $endpoint;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $data = json_decode($content, true) ?? [];

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('ABAS middleware request successful', [
                    'url' => $url,
                    'status_code' => $statusCode,
                    'portal_ref_id' => $data['portalRefID'] ?? null,
                ]);

                return [
                    'success' => !($data['error'] ?? false),
                    'statusCode' => $statusCode,
                    'data' => $data,
                    'error' => $data['errorMsg'] ?? '',
                ];
            }

            $this->logger->error('ABAS middleware returned error status', [
                'url' => $url,
                'status_code' => $statusCode,
                'response' => $content,
            ]);

            return [
                'success' => false,
                'statusCode' => $statusCode,
                'data' => $data,
                'error' => sprintf('HTTP %d: %s', $statusCode, $data['errorMsg'] ?? $content),
            ];
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('ABAS middleware connection failed', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'statusCode' => 0,
                'data' => [],
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
