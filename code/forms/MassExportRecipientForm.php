<?php
class MassExportRecipientForm extends Form {

    public function __construct($controller, $name) {
        $fields = FieldList::create(
            EmailField::create('to', 'To'),
            EmailField::create('cc', 'CC'),
            EmailField::create('bcc', 'BCC')
        );

        $actions = FieldList::create(
            FormAction::create('send', 'Continue Export & Send')
        );

        parent::__construct($controller, $name, $fields, $actions);
    }
}