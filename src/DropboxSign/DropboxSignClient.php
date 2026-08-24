<?php

namespace App\DropboxSign;

use Dropbox\Sign\Api\EmbeddedApi;
use Dropbox\Sign\Api\SignatureRequestApi;
use Dropbox\Sign\Api\TemplateApi;
use Dropbox\Sign\Api\UnclaimedDraftApi;
use Dropbox\Sign\ApiException;
use Dropbox\Sign\Configuration;
use App\Entity\FirmUserProfiles;
use Dropbox\Sign\Model\SubSigningOptions;
use Dropbox\Sign\Model\SubSignatureRequestSigner;
use Dropbox\Sign\Model\SignatureRequestCreateEmbeddedRequest;
use Dropbox\Sign\Model\SignatureRequestSendRequest;
use Dropbox\Sign\Model\SignatureRequestSendWithTemplateRequest;
use Dropbox\Sign\Model\SubCC;
use Dropbox\Sign\Model\SubFieldOptions;
use Dropbox\Sign\Model\SubSignatureRequestTemplateSigner;
use Dropbox\Sign\Model\SubTemplateRole;
use Dropbox\Sign\Model\TemplateCreateEmbeddedDraftRequest;
use Dropbox\Sign\Model\UnclaimedDraftCreateEmbeddedRequest;


final readonly class DropboxSignClient
{
    private Configuration $configuration;

    public function __construct(string $dropboxSignApiKey, private string $clientId, private bool $testMode)
    {
        $this->configuration = Configuration::getDefaultConfiguration()->setUsername($dropboxSignApiKey);
    }

    /**
     * ag: This will send a document straight up w/o form fields. Appends a page to the end of the document to sign. Very basic.
     *
     * @param string $filePath 
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function signatureRequestSend($filePath)
    {
        // ag: build the api configuration
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here

        // $field_options = (new SubFieldOptions())
        //     ->setDateFormat(SubFieldOptions::DATE_FORMAT_DD_MM_YYYY);

        $signing_options = (new SubSigningOptions())
            ->setDefaultType(SubSigningOptions::DEFAULT_TYPE_DRAW)
            ->setDraw(true)
            ->setPhone(false)
            ->setType(true)
            ->setUpload(true);

        $signers_1 = (new SubSignatureRequestSigner())
            ->setName("abel")
            ->setEmailAddress("agarrido84@gmail.com");
        // ->setOrder(0);

        // $signers_2 = (new SubSignatureRequestSigner())
        //     ->setName("abe")
        //     ->setEmailAddress("agarrido84@gmail.com")
        //     ->setOrder(1);

        $signers = [
            $signers_1,
            // $signers_2,
        ];

        $signature_request_send_request = (new SignatureRequestSendRequest())
            ->setMessage("Please sign this NDA and then we can discuss more. Let me know if you\nhave any questions.")
            ->setSubject("The NDA we talked about")
            ->setTestMode($this->testMode)
            ->setTitle("NDA with Acme Co.")
            // ->setCcEmailAddresses([
            //     "lawyer1@dropboxsign.com",
            // ])
            ->setFileUrls([
                'https://sccrtc.org/wp-content/uploads/2010/09/SampleContract-Shuttle.pdf',
            ])
            // ->setFiles([$filePath])
            // ->setMetadata(json_decode(<<<'EOD'
            // {
            //     "custom_id": 1234,
            //     "custom_text": "NDA #9"
            // }
            // EOD, true))
            // ->setFieldOptions($field_options)
            ->setSigningOptions($signing_options)
            ->setSigners($signers);

        try {
            $response = (new SignatureRequestApi(config: $this->configuration))->signatureRequestSend(
                signature_request_send_request: $signature_request_send_request,
            );

            print_r($response);
        } catch (ApiException $e) {
            echo "Exception when calling SignatureRequestApi#signatureRequestSend: {$e->getMessage()}";
        }
    }

    /**
     * ag: This will send a document straight up w/o form fields. Appends a page to the end of the document to sign.
     *
     * @param string $signatureRequestId  
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function signatureRequestCancel($signatureRequestId)
    {
        // ag: build the api configuration
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here

        try {
            (new SignatureRequestApi(config: $this->configuration))->signatureRequestCancel(
                signature_request_id: $signatureRequestId,
            );

            return true;
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Retrieves a downloadable or viewable PDF URL for a template.
     *
     * @param FirmUserProfiles $userProfile The user creating the template
     * @param ?string $filePath 
     * @param array $signatureRequestSigners 
     * @param string $title 
     * @param string $subject 
     * @param string $message 
     * @return object the signature request.
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function createEmbeddedSignatureRequest(FirmUserProfiles $userProfile, ?string $filePath = null, array $signatureRequestSigners, string $title, ?string $subject = 'Please sign this document', string $message)
    {

        // ag: this options sets what kind of signature the end user can perform.
        $signOptions = new SubSigningOptions();
        $signOptions
            ->setDefaultType(SubSigningOptions::DEFAULT_TYPE_DRAW)
            ->setDraw(true)
            ->setType(true)
            ->setUpload(true)
            ->setPhone(false);

        // ag: build out the signers
        $signers = array();
        foreach ($signatureRequestSigners as $key => $signer) {
            // dd($signer['name']);
            $requestSigner = new SubSignatureRequestSigner();
            $requestSigner->setName($signer['name'])
                ->setEmailAddress($signer['email'])
                ->setorder($key);

            $signers[] = $requestSigner;
        }

        // ag: set up the embbed request data
        $request = new SignatureRequestCreateEmbeddedRequest();
        $request->setClientId($this->clientId)
            ->setTitle($title)
            ->setSubject($subject)
            ->setMessage($message)
            ->setFileUrls([
                'https://sccrtc.org/wp-content/uploads/2010/09/SampleContract-Shuttle.pdf',
            ])
            // ->setFiles([$filePath])
            ->setSigners($signers)
            ->setSigningOptions($signOptions)
            ->setTestMode($this->testMode); // VERY IMPORTANT in dev

        // ag: build the api configuration
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here

        try {
            return (new SignatureRequestApi(config: $this->configuration))->signatureRequestCreateEmbedded(
                signature_request_create_embedded_request: $request,
            );
        } catch (ApiException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Retrieves a downloadable or viewable PDF URL for a template.
     *
     * When $forceDownload is true, the browser will download the PDF.
     * When false, the PDF will be displayed inline in the browser.
     *
     * @param string $templateId The Dropbox Sign template ID.
     * @param int   $forceDownload Whether to force file download (default: 1).
     * @return string The signed file URL.
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function getTemplateFileUrl(string $templateId, ?int $forceDownload = 1)
    {

        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey);

        try {
            return (new TemplateApi(config: $this->configuration))->templateFilesAsFileUrl(
                template_id: $templateId,
                force_download: $forceDownload,
            );
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Retrieves a list of all templates associate with the client account.
     * 
     * @return object The list of created templates.
     *
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function getTemplateList()
    {
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey);

        try {
            return (new TemplateApi(config: $this->configuration))->templateList(
                page: 1,
                page_size: 20,
            );
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Deletes a created template.
     * @param string $templateId The Dropbox Sign template ID.
     *
     * @return void
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function deleteTemplate($templateId)
    {
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey);

        try {
            return (new TemplateApi(config: $this->configuration))->templateDelete(
                template_id: $templateId,
            );
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Retrieves a downloadable or viewable PDF URL for a template.
     *
     * When $forceDownload is true, the browser will download the PDF.
     * When false, the PDF will be displayed inline in the browser.
     *
     * @param string $filePath The Dropbox Sign template ID.
     * @param string $templateTitle 
     * @param string $templateSubject 
     * @param string $templateMessage 
     * @param array $templateSignerRoles
     * @param string $templateCcRole 
     * @param bool $templatesSignerOrder 
     *
     * @return object The signed file URL.
     *
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function createTemplate($filePath, $templateTitle, $templateSubject, $templateMessage, array $templateSignerRoles, ?string $templateCcRole = null, ?bool $templatesSignerOrder = false)
    {
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here


        $fieldOptions = (new SubFieldOptions())
            ->setDateFormat(SubFieldOptions::DATE_FORMAT_DD_MM_YYYY);

        // ag: unclear on what these do.
        // $merge_fields_1 = (new SubMergeField())
        //     ->setName("Full Name")
        //     ->setType(SubMergeField::TYPE_TEXT);

        // $merge_fields_2 = (new SubMergeField())
        //     ->setName("Is Registered?")
        //     ->setType(SubMergeField::TYPE_CHECKBOX);

        // $merge_fields = [
        //     $merge_fields_1,
        //     $merge_fields_2,
        // ];


        $signersRoles = array();
        foreach ($templateSignerRoles as $key => $value) {
            $signer = new SubTemplateRole();
            $signer->setName($value);
            if ($templatesSignerOrder == true) {
                $signer->setOrder($key);
            }
            $signersRoles[] = $signer;
        }

        // dd($signersRoles);
        $templateCreateEmbeddedDraftRequest = (new TemplateCreateEmbeddedDraftRequest())
            ->setClientId($this->clientId)
            ->setTitle($templateTitle)
            ->setSubject($templateSubject)
            ->setMessage($templateMessage)
            // ->setFileUrls([
            //     'https://sccrtc.org/wp-content/uploads/2010/09/SampleContract-Shuttle.pdf',
            // ])
            ->setFiles([$filePath])
            ->setFieldOptions($fieldOptions)
            // ->setMergeFields($merge_fields)
            ->setSignerRoles($signersRoles)
            ->setTestMode($this->testMode);

        if (isset($templateCcRole) && !is_null($templateCcRole)) {
            $templateCreateEmbeddedDraftRequest->setCcRoles([
                $templateCcRole
            ]);
        }

        try {
            return (new TemplateApi(config: $this->configuration))->templateCreateEmbeddedDraft(
                template_create_embedded_draft_request: $templateCreateEmbeddedDraftRequest,
            );
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Deletes a created template.
     *
     * @param string $signatureId This is the id for the signer user, not the document id.
     * @return object
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function getEmbeddedSignUrl(string $signatureId, string $clientId)
    {

        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here

        try {
            $response =  (new EmbeddedApi(config: $this->configuration))->embeddedSignUrl(
                $signatureId,
                $clientId
            );
            return $response->getEmbedded()->getSignUrl();
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Retrieves an embedded template with the uploaded file. Returns a claim_url that will walk the user through the process of setting up the form via the api.
     *
     * @param string $requesterEmailAddress 
     * @param string $filePath
     *  
     * @return object the signature request.
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    // public function createUnclaimedDraftCreateEmbedded(string $requesterEmailAddress, string $filePath): Object
    // {

    //     $unclaimed_draft_create_embedded_request = (new UnclaimedDraftCreateEmbeddedRequest())
    //         ->setClientId($this->clientId)
    //         // ag: TODO once I have a paid plan change this to the fup who makes the docu sign request.
    //         ->setRequesterEmailAddress("agarrido84@gmail.com")
    //         // ->setRequesterEmailAddress($requesterEmailAddress)
    //         ->setTestMode($this->testMode)
    //         // ->setFileUrls([
    //         //     'https://sccrtc.org/wp-content/uploads/2010/09/SampleContract-Shuttle.pdf',
    //         // ]);
    //         ->setFiles([$filePath]);

    //     try {
    //         return (new UnclaimedDraftApi(config: $this->configuration))->unclaimedDraftCreateEmbedded(
    //             unclaimed_draft_create_embedded_request: $unclaimed_draft_create_embedded_request,
    //         );
    //     } catch (ApiException $e) {
    //         throw new \RuntimeException(
    //             'Dropbox Sign API failed: ' . $e->getMessage(),
    //             previous: $e
    //         );
    //     }
    // }

    public function createUnclaimedDraftCreateEmbedded(string $requesterEmailAddress, string $filePath): object
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException(
                sprintf('Signature document does not exist: %s', $filePath)
            );
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException(
                sprintf('Signature document is not readable: %s', $filePath)
            );
        }

        // ag: populate the request to be sent
        $request = (new UnclaimedDraftCreateEmbeddedRequest())
            ->setClientId($this->clientId)
            // ag: TODO once I have a paid plan change this to the fup who makes the docu sign request.
            // ->setRequesterEmailAddress($requesterEmailAddress)
            ->setRequesterEmailAddress("agarrido84@gmail.com")
            ->setTestMode($this->testMode)
            ->setFiles([$filePath]);

        try {
            return (new UnclaimedDraftApi(config: $this->configuration))->unclaimedDraftCreateEmbedded(unclaimed_draft_create_embedded_request: $request);
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @param string $signatureRequestId Obtain a copy of the current documents specified by the signature_request_id parameter. 
     * @return object a PDF or ZIP file. If the files are currently being prepared, a status code of 409 will be returned instead.
     */
    public function downloadSignatureRequestFile($signatureRequestId)
    {

        try {
            return (new SignatureRequestApi(config: $this->configuration))->signatureRequestFiles(
                signature_request_id: $signatureRequestId,
                // file_type: "pdf",
            );

            // copy($response->getRealPath(), __DIR__ . '/file_response');
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function signatureRequestRemove(string $signatureRequestId)
    {
        // ag: build the api configuration
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here

        try {
            (new SignatureRequestApi(config: $this->configuration))->signatureRequestRemove(
                signature_request_id: $signatureRequestId,
            );
        } catch (ApiException $e) {
            echo "Exception when calling SignatureRequestApi#signatureRequestRemove: {$e->getMessage()}";
        }
    }

    // public function signatureRequestCreateEmbeddedWithTemplate($templateId, string $templateSubject, string $templateMessage, array $templateSigners, ?array $ccUser = null)
    // {
    //     // ag: build the api configuration
    //     $config = Configuration::getDefaultConfiguration()
    //         ->setUsername($this->apiKey); // API key goes here

    //     $signing_options = (new SubSigningOptions())
    //         ->setDefaultType(SubSigningOptions::DEFAULT_TYPE_DRAW)
    //         ->setDraw(true)
    //         ->setPhone(false)
    //         ->setType(true)
    //         ->setUpload(true);


    //     $signers = [];
    //     foreach ($templateSigners as $signer) {
    //         $signers[] = (new SubSignatureRequestTemplateSigner())
    //             ->setRole($signer['signer_role'])
    //             ->setName($signer['name'])
    //             ->setEmailAddress($signer['email']);
    //     }

    //     $signature_request_create_embedded_with_template_request = (new SignatureRequestCreateEmbeddedWithTemplateRequest())
    //         ->setClientId($this->clientId)
    //         ->setTemplateIds([
    //             $templateId,
    //         ])
    //         ->setMessage($templateMessage)
    //         ->setSubject($templateSubject)
    //         ->setTestMode($this->testMode)
    //         ->setSigningOptions($signing_options)
    //         ->setSigners($signers)
    //         // ->setCcs([
    //         //     [
    //         //         "role" => "agarrido84+optional@gmail.com",
    //         //         'name' => 'Abe',
    //         //         "email_address" => "agarrido84@gmail.com"
    //         //     ]
    //         // ])
    //     ;

    //     try {
    //         $response = (new SignatureRequestApi(config: $config))->signatureRequestCreateEmbeddedWithTemplate(
    //             signature_request_create_embedded_with_template_request: $signature_request_create_embedded_with_template_request,
    //         );

    //         print_r($response);
    //     } catch (ApiException $e) {
    //         echo "Exception when calling SignatureRequestApi#signatureRequestCreateEmbeddedWithTemplate: {$e->getMessage()}";
    //     }
    // }

    /**
     * Creates and sends a new SignatureRequest based off of the Template
     * 
     * @param int $templateId The template id to be used.
     * @param string $templateSubject
     * @param string $templateMessage
     * @param array $templateSigners array of all the signers
     * @param ?array $ccUser.
     * @return object The returned api call from dropbox sign.
     * @throws \RuntimeException When the template file cannot be retrieved.
     */
    public function signatureRequestSendWithTemplate($templateId, string $templateSubject, string $templateMessage, array $templateSigners, ?array $ccUser = null)
    {
        // ag: build the api configuration
        // $config = Configuration::getDefaultConfiguration()->setUsername($this->apiKey); // API key goes here

        $signing_options = (new SubSigningOptions())
            ->setDefaultType(SubSigningOptions::DEFAULT_TYPE_DRAW)
            ->setDraw(true)
            ->setPhone(false)
            ->setType(true)
            ->setUpload(true);

        $signers = [];
        foreach ($templateSigners as $signer) {
            $signers[] = (new SubSignatureRequestTemplateSigner())
                ->setRole($signer['signer_role'])
                ->setName($signer['name'])
                ->setEmailAddress($signer['email']);
        }

        $ccs = [];
        if (!is_null($ccUser) && !empty($ccUser)) {
            $ccs_1 = (new SubCC())
                ->setRole($ccUser[0]['role'])
                ->setEmailAddress($ccUser[0]['email']);

            $css[] = $ccs_1;
        }

        $signature_request_send_with_template_request = (new SignatureRequestSendWithTemplateRequest())
            ->setTemplateIds([
                $templateId,
            ])
            ->setMessage($templateMessage)
            ->setSubject($templateSubject)
            ->setTestMode($this->testMode)
            ->setSigningOptions($signing_options)
            ->setSigners($signers);

        if (!empty($css)) {
            $signature_request_send_with_template_request->setCcs($css);
        }

        try {
            return (new SignatureRequestApi(config: $this->configuration))->signatureRequestSendWithTemplate(
                signature_request_send_with_template_request: $signature_request_send_with_template_request,
            );
        } catch (ApiException $e) {
            throw new \RuntimeException(
                'Dropbox Sign API failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }
}
