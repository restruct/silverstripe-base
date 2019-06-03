<?php
namespace LeKoala\Base\Forms;

/**
 * Format date field using ISO value
 *
 * Locale conversion cannot be done by InputMask and should be provided by a third party service
 */
class InputMaskDateField extends InputMaskDateTimeField
{
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);

        $this->setInputFormat(self::getDefaultInputFormat());
        // use ISO date format when unmasking to ensure proper data storage in the db
        $this->setOutputFormat('yyyy-mm-dd');
    }

    public function setValue($value, $data = null)
    {
        return parent::setValue($value, $data);
    }
}
