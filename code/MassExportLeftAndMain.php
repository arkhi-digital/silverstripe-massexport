<?php

namespace SteadLane\MassExport;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\View\Requirements;

/**
 * Class MassExportLeftAndMain
 * @package SteadLane\MassExport
 */
class MassExportLeftAndMain extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'export';
    private static $menu_title = 'Mass Export';

    private static $allowed_actions = array(
        'index',
        'ExportForm',
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
        return MassExportForm::create($this, __FUNCTION__);
    }

    /**
     * @return MassExportRecipientForm
     */
    public function RecipientForm()
    {
        return MassExportRecipientForm::create($this, __FUNCTION__);
    }

    /**
     * Form handling
     *
     * @param array $data
     * @param Form $form
     * @return \SilverStripe\Control\HTTPResponse|null
     */
    public function processExportForm(array $data, Form $form)
    {
        $vars = $this->request->postVars();

        $filter = array(
            'Created:GreaterThanOrEqual' => date('Y-m-d H:i:s', strtotime($vars['exportFrom'])),
            'Created:LessThanOrEqual' => date('Y-m-d H:i:s', strtotime($vars['exportTo'] . " 23:59:59"))
        );

        $fileCount = 0;
        $models = $vars['modelsToExport'];
        $udfs = array();

        foreach ($models as $key => $model) {
            if (!strstr($model, 'UDF_')) {
                continue;
            }

            unset($models[$key]);
            $udfs[] = (int)str_replace('UDF_', '', $model);
        }

		// @TODO: default this somewhere else; also allow changing it in config
        $zip = new \ZipArchive();
        $zipFilename = __DIR__ . "/../exports/MassExport_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.zip';
        if (file_exists($zipFilename)) {
            @unlink($zipFilename);
        }

        $zip->open($zipFilename, \ZipArchive::CREATE);

        foreach ($models as $model) {
            /** @var DataObject $model */
            $records = $model::get()->filter($filter);

            if (!is_array($model::config()->db)) {
                continue;
            }

            //$modelName=basename(str_replace('\\','/',$model));
            $modelName=str_replace('\\','_',$model);
            if (substr($modelName,0,1)=='_') {
                $modelName=substr($modelName,1);
            }
            $filename = __DIR__ . "/../exports/{$modelName}_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.csv';

            $fp = fopen($filename, "w+");


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
            $fileCount++;

        }

        foreach ($udfs as $pageId) {
            /** @var DataList $submissions */
            $submissions = SubmittedForm::get()->filter(
                array(
                    'ParentID' => $pageId,
                    'Created:GreaterThanOrEqual' => date('Y-m-d H:i:s', strtotime($vars['exportFrom'])),
                    'Created:LessThanOrEqual' => date('Y-m-d H:i:s', strtotime($vars['exportTo'] . " 23:59:59"))
                )
            );

            if (!$submissions->count()) {
                continue;
            }

            /** @var SiteTree $page */
            $page = SiteTree::get()->byID($pageId);
            $title = $page->Title;
            $this->sanitiseTitleName($title);

            $filename = __DIR__ . "/../exports/UDF_{$title}_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.csv';

            $heading = array('Created', 'LastEdited');

            $first = clone $submissions;
            $first = $first->first();
            /** @var SubmittedForm $first */
            $firstValues = $first->Values();

            foreach ($firstValues as $value) {
                $heading[] = $value->Title;
            }

            $fp = fopen($filename, "w+");
            fputcsv($fp, $heading);

            foreach ($submissions as $submission) {
                $values = $submission->Values();

                $row = array();
                $row[] = $submission->Created;
                $row[] = $submission->LastEdited;

                foreach ($values as $value) {
                    $row[] = $value->Value;
                }

                fputcsv($fp, $row);
            }

            fclose($fp);
            $zip->addFile($filename, basename($filename));
            $fileCount++;
        }

        $zip->close();

        if (!$fileCount) {
            if (file_exists($zipFilename)) {
                unlink($zipFilename);
            }
            $form->sessionMessage('No records were found within that date range. Export not available.', 'bad');
            return $this->redirectBack();
        }

        if (isset($vars['action_processExportForm'])) {

            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename='.basename($zipFilename));
            header('Content-Length: ' . filesize($zipFilename));
            readfile($zipFilename);
        }

    }

    public function sanitiseTitleName(&$title) {
        $title = preg_replace('/\s+/i', '_', $title);
        $title = preg_replace("/[^A-Za-z0-9_]/", '', $title);
    }

    public function InfoMessage()
    {
        if (!class_exists('ZipArchive')) {
            return "'ZipArchive' PHP class not found. Zip functionality will not work.";
        }
        return '';
    }

    public function InfoMessageCls()
    {
        if (!class_exists('ZipArchive')) {
            return 'danger';
        }
        return '';
    }
}