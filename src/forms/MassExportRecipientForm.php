<?php

namespace SteadLane\MassExport;

use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;

class MassExportRecipientForm extends Form {

    public function __construct($controller, $name) {
        $fields = FieldList::create(
            EmailField::create('to', 'To'),
            EmailField::create('cc', 'CC'),
            EmailField::create('bcc', 'BCC')
        );

        $actions = FieldList::create(
            FormAction::create('send', 'Continue Export & Send')->addExtraClass('btn btn-primary action')
        );

        parent::__construct($controller, $name, $fields, $actions);
    }
}