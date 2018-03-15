<?php
namespace gorriecoe\DataObjectHistory\Forms;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;

/**
 * DataObjectHistory
 *
 * @package silverstripe-dataobjecthistory
 */
class HistoryGridFieldItemRequest extends VersionedGridFieldItemRequest
{
    /**
     * Defines methods that can be called directly
     * @var array
     */
    private static $allowed_actions = [
        'view' => true,
        'ItemEditForm' => true
    ];

    /**
     * @var int
     */
    protected $versionID;

    public function __construct($gridField, $component, $record, $requestHandler, $popupFormName)
    {
        if ($this->versionID = $requestHandler->getRequest()->requestVar('VersionID')) {
            if (!$record = Versioned::get_version(get_class($record), $record->ID, $this->versionID)) {
                return $requestHandler->httpError(
                    404,
                    _t(__CLASS__ . '.InvalidVersion', 'Invalid version')
                );
            }
        }
        parent::__construct($gridField, $component, $record, $requestHandler, $popupFormName);
    }

    public function view($request)
    {
        if (!$this->record->canView()) {
            $this->httpError(403);
        }
        $controller = $this->getToplevelController();
        $form = $this->ItemEditForm();
        $data = ArrayData::create([
            'Backlink' => $controller->Link(),
            'ItemEditForm' => $form
        ]);
        $return = $data->renderWith($this->getTemplates());
        if ($request->isAjax()) {
            return $return;
        }
        return $controller->customise(['Content' => $return]);
    }

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $fields = $form->Fields();
        $record = $this->record;
        $fields->push(
            HiddenField::create(
                'VersionID',
                'VersionID',
                $record->Version
            )
        );
        $fields->addFieldToTab(
            'Root.Main',
            ReadonlyField::create(
                'Sort',
                _t(__CLASS__ . '.Position', 'Position'),
                $record->Sort
            )
        );

        $fields = $fields->makeReadonly();

        if ($record->isLatestVersion()) {
            $message = _t(__CLASS__ . '.VIEWINGLATEST', 'Currently viewing the latest version.');
        } else {
            $message = _t(
                __CLASS__ . '.VIEWINGVERSION',
                "Currently viewing version {version}.",
                ['version' => $this->versionID]
            );
        }

        $fields->unshift(
            LiteralField::create(
                'CurrentlyViewingMessage',
                ArrayData::create([
                    'Content' => DBField::create_field('HTMLFragment', $message),
                    'Classes' => 'notice'
                ])
                ->renderWith('Silverstripe\\CMS\\Controllers\\Includes\\CMSMain_notice')
            )
        );

        $form->setFields($fields);
        return $form;
    }

    public function doRollback($data, $form)
    {
        $record = $this->record;

        // Check permission
        if (!$record->canEdit()) {
            return $this->httpError(403);
        }

        // Save from form data
        $record->doRollbackTo($record->Version);
        $link = '<a href="' . $this->Link('edit') . '">"'
            . htmlspecialchars($record->Title, ENT_QUOTES)
            . '"</a>';

        $message = _t(
            __CLASS__ . '.RolledBack',
            'Rolled back {name} to version {version} {link}',
            array(
                'name' => $record->i18n_singular_name(),
                'version' => $record->Version,
                'link' => $link
            )
        );

        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);

        $controller = $this->getToplevelController();
        return $controller->redirect($record->CMSEditLink());
    }

    public function getFormActions()
    {
        $record = $this->getRecord();
        if (!$record || !$record->has_extension(Versioned::class)) {
            return $actions;
        }

        $this->beforeExtending('updateFormActions', function (FieldList $actions) use ($record) {
            if (!$record->isLatestVersion()) {
                $actions->removeByName([
                    'action_doUnpublish',
                    'action_doUnpublish',
                    'action_doDelete',
                    'action_doSave',
                    'action_doPublish',
                    'action_doArchive'
                ]);
            }
            if ($record->canEdit()) {
                $actions->push(
                    FormAction::create(
                        'doRollback',
                        _t(__CLASS__ . '.REVERT', 'Revert to this version')
                    )
                        ->setUseButtonTag(true)
                        ->setDescription(_t(
                            __CLASS__ . '.BUTTONREVERTDESC',
                            'Publish this record to the draft site'
                        ))
                        ->addExtraClass('btn-warning font-icon-back-in-time')
                );
            }
        });

        $actions = parent::getFormActions();
        return $actions;
    }
}
