<?php
namespace LeKoala\Base\Actions;

use SilverStripe\Forms\FormAction;
use SilverStripe\Core\Convert;

/**
 * Custom actions to use in getCMSActions
 *
 * Actions handlers are declared on the DataObject itself
 *
 * Because it is an action, it will be submitted through ajax
 * If you want to create links that open files or show a new page, use CustomLink
 */
class CustomAction extends FormAction
{
    use CustomButton;

    /**
     * @var boolean
     */
    public $useButtonTag = true;

    /**
     * Used in ActionsGridFieldItemRequest::forwardActionToRecord
     * @var boolean
     */
    protected $shouldRefresh = false;

    public function __construct($name, $title, $form = null)
    {
        // Actually, an array works just fine!
        $name = 'doCustomAction[' . $name . ']';

        parent::__construct($name, $title, $form);
    }

    public function actionName()
    {
        return rtrim(str_replace('action_doCustomAction[', '', $this->name), ']');
    }

    /**
     * Get the title of the button (without icons or anything)
     * Called by ActionsGridFieldItemRequest to build default message
     */
    public function getTitle()
    {
        return $this->title;
    }

    public function Field($properties = array())
    {
        if ($this->buttonIcon) {
            $this->buttonContent = $this->getButtonTitle();
        }
        // Note: type should stay "action" to properly submit
        $this->addExtraClass('custom-action');
        if ($this->confirmation) {
            $this->setAttribute("data-confirm", Convert::raw2htmlatt($this->confirmation));
        }
        return parent::Field($properties);
    }

    /**
     * Get the value of shouldRefresh
     * @return mixed
     */
    public function getShouldRefresh()
    {
        return $this->shouldRefresh;
    }

    /**
     * Set the value of shouldRefresh
     *
     * @param mixed $shouldRefresh
     * @return $this
     */
    public function setShouldRefresh($shouldRefresh)
    {
        $this->shouldRefresh = $shouldRefresh;
        return $this;
    }
}
