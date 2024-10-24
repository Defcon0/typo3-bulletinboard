<?php
declare(strict_types=1);

namespace WapplerSystems\WsBulletinboard\Form\Factory;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;
use TYPO3\CMS\Extbase\Validation\Validator\StringLengthValidator;
use TYPO3\CMS\Form\Domain\Configuration\ConfigurationService;
use TYPO3\CMS\Form\Domain\Factory\AbstractFormFactory;
use TYPO3\CMS\Form\Domain\Finishers\EmailFinisher;
use TYPO3\CMS\Form\Domain\Finishers\RedirectFinisher;
use TYPO3\CMS\Form\Domain\Finishers\SaveToDatabaseFinisher;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\GridRow;
use TYPO3\CMS\Form\Domain\Model\FormElements\Section;
use TYPO3\CMS\Form\Domain\Renderer\FluidFormRenderer;
use TYPO3\CMS\Form\Mvc\Validation\FileSizeValidator;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use WapplerSystems\FormExtended\Domain\Finishers\AttachUploadsToObjectFinisher;
use WapplerSystems\FormExtended\Mvc\Validation\FileCollectionSizeValidator;
use WapplerSystems\FormExtended\Mvc\Validation\FileCountValidator;
use WapplerSystems\WsBulletinboard\Exception\MissingConfigurationException;

class BulletinboardFormFactory extends AbstractFormFactory
{


    /**
     * @param array $configuration
     * @param string|null $prototypeName
     * @return FormDefinition
     * @throws \TYPO3\CMS\Extbase\Validation\Exception\InvalidValidationOptionsException
     * @throws \TYPO3\CMS\Form\Domain\Configuration\Exception\PrototypeNotFoundException
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotFoundException
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotValidException
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FinisherPresetNotFoundException
     * @throws MissingConfigurationException
     */
    public function build(array $configuration, string $prototypeName = null): FormDefinition
    {

        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
        $prototypeConfiguration = $configurationService->getPrototypeConfiguration('bulletinboard');

        $formDefinition = GeneralUtility::makeInstance(FormDefinition::class, 'bulletinboardEntryForm', $prototypeConfiguration);
        $formDefinition->setRendererClassName(FluidFormRenderer::class);
        $formDefinition->setRenderingOption('controllerAction', 'new');
        $formDefinition->setRenderingOption('submitButtonLabel', 'Submit');


        if (empty($configuration['frameworkConfiguration']['persistence']['storagePid'])) {
            throw new MissingConfigurationException('No storagePid set', 1627843908);
        }
        if (($configuration['storageFolder'] ?? '') === '') {
            throw new MissingConfigurationException('No storage folder set', 1627843909);
        }

        $actionKey = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(30);

        $context = GeneralUtility::makeInstance(Context::class);

        $recipients = [];
        $recipientsFlexform = $configuration['verification']['recipients'] ?: [];

        foreach ($recipientsFlexform as $recipient) {
          $recipients[$recipient['container']['address']] = $recipient['container']['name'];
        }

        /** @var SaveToDatabaseFinisher $saveToDatabaseFinisher */
        $saveToDatabaseFinisher = $formDefinition->createFinisher('SaveToDatabase');
        $saveToDatabaseFinisher->setOptions([
            'table' => 'tx_wsbulletinboard_domain_model_entry',
            'mode' => 'insert',
            'databaseColumnMappings' => [
                'pid' => [
                    'value' => $configuration['frameworkConfiguration']['persistence']['storagePid'],
                ],
                'tstamp' => [
                    'value' => time(),
                ],
                'crdate' => [
                    'value' => time(),
                ],
                'action_key' => [
                    'value' => $actionKey,
                ],
                'hidden' => [
                    'value' => ($configuration['automaticApproval'] === '1' || empty($recipients)) ? 0 : 1,
                ],
                'fe_user' => [
                    'value' => $context->getPropertyFromAspect('frontend.user', 'id'),
                ],
                'images' => [
                    'value' => 0,
                ],
            ],

            'elements' => [
                'title' => [
                    'mapOnDatabaseColumn' => 'title',
                ],
                'name' => [
                    'mapOnDatabaseColumn' => 'name',
                ],
                'message' => [
                    'mapOnDatabaseColumn' => 'message',
                ],
            ]
        ]);

        /** @var AttachUploadsToObjectFinisher $attachUploadsToObjectFinisher */
        $attachUploadsToObjectFinisher = $formDefinition->createFinisher('AttachUploadsToObject');
        $attachUploadsToObjectFinisher->setOptions([
            'elements' => [
                'images' => [
                    'table' => 'tx_wsbulletinboard_domain_model_entry',
                    'mapOnDatabaseColumn' => 'images',
                    'lastInsertId' => true,
                ],
            ]
        ]);

        $defaultFrom = MailUtility::getSystemFrom();
        if (isset($defaultFrom[0])) {
            $defaultFrom = [$defaultFrom[0] => 'no sendername'];
        }

        if (!empty($configuration['verification']['email']['senderEmailAddress'])) {
            $defaultFrom = [$configuration['verification']['email']['senderEmailAddress'] => $configuration['verification']['email']['senderName']];
        }

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $confirmationUrl = $uriBuilder->reset()
            ->setTargetPageUid($configuration['pageUid'])
            ->setCreateAbsoluteUri(true)
            ->setArguments([
                'tx_wsbulletinboard_form' => [
                    'action' => 'confirm',
                    'controller' => 'Bulletinboard',
                    'action_key' => $actionKey,
                ],
            ])
            ->buildFrontendUri();

        $declineUrl = $uriBuilder->reset()
            ->setTargetPageUid($configuration['pageUid'])
            ->setCreateAbsoluteUri(true)
            ->setArguments([
                'tx_wsbulletinboard_form' => [
                    'action' => 'decline',
                    'controller' => 'Bulletinboard',
                    'action_key' => $actionKey,
                ],
            ])
            ->buildFrontendUri();

        if (!empty($recipients)) {
          /** @var EmailFinisher $emailFinisher */
          $emailFinisher = $formDefinition->createFinisher('EmailToReceiver');
          $emailFinisher->setOptions([
            'subject' => $configuration['verification']['email']['subject'],
            'recipients' => $recipients,
            'senderName' => $defaultFrom[array_key_first($defaultFrom)],
            'senderAddress' => array_key_first($defaultFrom),
            'useFluidEmail' => true,
            'attachUploads' => false,
            'templateName' => 'Notification',
            'templateRootPaths' => [
              50 => 'EXT:ws_bulletinboard/Resources/Private/Templates/Email/',
            ],
            'variables' => [
              'confirmationUrl' => $confirmationUrl,
              'declineUrl' => $declineUrl,
            ]
          ]);
        }

        /** @var RedirectFinisher $redirectFinisher */
        $redirectFinisher = $formDefinition->createFinisher('Redirect');
        $redirectFinisher->setOptions([
            'pageUid' => $configuration['pageUid'],
            'additionalParameters' => 'tx_wsbulletinboard_form[action]=done',
        ]);

        $page = $formDefinition->createPage('page1');

        /** @var GridRow $row */
        $row = $page->createElement('row1', 'GridRow');

        /** @var Section $fieldset */
        $fieldset = $row->createElement('fieldsetEntry', 'Fieldset');
        $fieldset->setLabel('New Bulletinboard Entry');
        $fieldset->setOptions(['properties' => [
            'gridColumnClassAutoConfiguration' => [
                'viewPorts' => [
                    'md' => 12
                ]
            ]
        ]]);

        /** @var GenericFormElement $element */
        $element = $fieldset->createElement('title', 'Text');
        $element->setLabel('Title');
        $element->setProperty('required', true);
        $element->addValidator(new StringLengthValidator(['maximum' => 500]));
        $element->addValidator(new NotEmptyValidator());

        $element = $fieldset->createElement('images', 'FileUpload');
        $element->setLabel('Images');
        $element->setProperty('multiple', true);
        $element->setProperty('allowedMimeTypes', ['image/jpg', 'image/jpeg']);
        $element->setProperty('saveToFileMount', $configuration['storageFolder']);


        $maxUploadFileSizeString = trim($configuration['fields']['images']['maxUploadFileSize'] ?? '');
        $maxUploadFileSize = 0;
        if ($maxUploadFileSizeString !== '') {
            $maxUploadFileSize = $this->humanReadableToBytes($maxUploadFileSizeString) / 1024;
        }
        if ($maxUploadFileSize === 0 || $maxUploadFileSize > GeneralUtility::getMaxUploadFileSize()) {
            $maxUploadFileSize = GeneralUtility::getMaxUploadFileSize();
        }
        $fluidAdditionalAttributes = [
            'data-min-filesize' => 0,
            'data-max-filesize' => $maxUploadFileSize * 1024,
            'data-msg-filesize-exceeded' => LocalizationUtility::translate('msg.filesizeExceeded', 'ws_bulletinboard', [$this->bytesToString($maxUploadFileSize * 1024)]),
        ];

        $maxFiles = (int)($configuration['fields']['images']['maxFiles'] ?? 0);
        $maxSizePerFile = 0;
        $maxSizePerFileString = ($configuration['fields']['images']['maxSizePerFile'] ?? '');
        if ($maxSizePerFileString !== '') {
            $maxSizePerFile = $this->humanReadableToBytes($maxSizePerFileString) / 1024;
        }

        if ($maxSizePerFile > 0) {
            //$element->addValidator(new FileSizeValidator(['maximum' => $maxUploadFileSize . 'K']));
            $fluidAdditionalAttributes['data-min-filesize-per-file'] = 0;
            $fluidAdditionalAttributes['data-max-filesize-per-file'] = $maxSizePerFile * 1024;
        }
        $element->addValidator(new FileCollectionSizeValidator(['maximum' => $maxUploadFileSize . 'K']));
        if ($maxFiles > 0) {
            //$element->addValidator(new FileCountValidator(['maximum' => 4]));
            $fluidAdditionalAttributes['data-min-files'] = 0;
            $fluidAdditionalAttributes['data-max-files'] = $maxFiles;
            $fluidAdditionalAttributes['data-msg-files-limit'] = LocalizationUtility::translate('msg.filesLimit', 'ws_bulletinboard', [0, $maxFiles]);
        }
        $element->setProperty('fluidAdditionalAttributes', $fluidAdditionalAttributes);

        /** @var GenericFormElement $element */
        $element = $fieldset->createElement('message', 'Textarea');
        $element->setLabel('Message');
        $element->setProperty('rows', '4');
        $element->setProperty('elementClassAttribute', 'form-control-bstextcounter');
        $element->setProperty('fluidAdditionalAttributes', ['maxlength' => (int)($configuration['fields']['message']['maxCharacters'] ?? PHP_INT_MAX), 'minlength' => (int)($configuration['fields']['message']['minCharacters'] ?? 0)]);
        $element->addValidator(new NotEmptyValidator());
        $element->addValidator(new StringLengthValidator(['minimum' => (int)($configuration['fields']['message']['minCharacters'] ?? 50), 'maximum' => (int)($configuration['fields']['message']['maxCharacters'] ?? PHP_INT_MAX)]));


        $this->triggerFormBuildingFinished($formDefinition);

        return $formDefinition;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }


    private function humanReadableToBytes(string $size)
    {
        $units = [
            'B' => 1,
            'KB' => 1024,
            'MB' => 1024 * 1024,
            'GB' => 1024 * 1024 * 1024,
            'TB' => 1024 * 1024 * 1024 * 1024,
            'PB' => 1024 * 1024 * 1024 * 1024 * 1024,
        ];

        // Den Wert und die Einheit separieren
        $number = (float)preg_replace('/[^0-9\.]/', '', $size);
        $unit = strtoupper(trim(preg_replace('/[0-9\.]/', '', $size)));

        // Überprüfen, ob die angegebene Einheit gültig ist
        if (!array_key_exists($unit, $units)) {
            throw new \Exception("Unknown file unit: $unit");
        }

        // Wert in Bytes umwandeln
        return $number * $units[$unit];
    }

    private function bytesToString(float $bytes, $decimals = 0, $decimalSeparator = '.', $thousandsSeparator = ',', $units = null)
    {
        if ($units === null) {
            $units = LocalizationUtility::translate('viewhelper.format.bytes.units', 'fluid');
        }
        $units = GeneralUtility::trimExplode(',', $units, true);

        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 2 ** (10 * $pow);

        return sprintf(
            '%s %s',
            number_format(
                round($bytes, 4 * $decimals),
                (int)$decimals,
                $decimalSeparator,
                $thousandsSeparator
            ),
            $units[$pow]
        );

    }

}
