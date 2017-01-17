<?php

class MassExportLeftAndMain extends LeftAndMain implements PermissionProvider
{

    private static $url_segment = 'export';
    private static $menu_title = 'Mass Export';

    private static $allowed_actions = array(
        'index',
        'processExportForm'
    );

    /**
     * Add our requirements
     */
    public function init()
    {
        parent::init();
        Requirements::css('massexport/css/massexport.css');
        Requirements::javascript('massexport/javascript/massexport.js');
    }

    /**
     * @return MassExportForm
     */
    public function ExportForm()
    {
        return MassExportForm::create($this, 'processExportForm');
    }

    /**
     * @return MassExportRecipientForm
     */
    public function RecipientForm()
    {
        return MassExportRecipientForm::create($this, 'processExportForm');
    }

    /**
     * Form handling
     *
     * @param SS_HTTPRequest $data
     * @param Form $form
     */
    public function processExportForm(SS_HTTPRequest $data, Form $form)
    {
        $vars = $this->request->postVars();

        $filter = array(
            'Created:GreaterThanOrEqual' => date('Y-m-d H:i:s', strtotime($vars['exportFrom'])),
            'Created:LessThanOrEqual' => date('Y-m-d H:i:s', strtotime($vars['exportTo'] . " 23:59:59"))
        );

        $models = $vars['modelsToExport'];

        $zip = new ZipArchive();
        $zipFilename = __DIR__ . "/../exports/MassExport_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.zip';
        $zip->open($zipFilename, ZipArchive::CREATE);

        foreach ($models as $model) {
            /** @var DataObject $model */
            $records = $model::get()->filter($filter);

            $filename = __DIR__ . "/../exports/{$model}_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.csv';

            $fp = fopen($filename, "w+");

            if (!is_array($model::config()->db)) {
                continue;
            }

            $headers = array_merge(array('Created', 'LastEdited'), array_keys($model::config()->db));
            fputcsv($fp, $headers);

            $db = array_keys($model::config()->db);

            foreach ($records as $record) {
                $line = array(
                    $record->Created,
                    $record->LastEdited,
                );

                foreach ($db as $key) {
                    $line[] = $record->{$key};
                }

                fputcsv($fp, $line);
            }

            fclose($fp);

            $zip->addFile($filename, basename($filename));

        }

        $zip->close();

        if (isset($vars['action_export'])) {
            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename='.basename($zipFilename));
            header('Content-Length: ' . filesize($zipFilename));
            readfile($zipFilename);
        }

    }
}