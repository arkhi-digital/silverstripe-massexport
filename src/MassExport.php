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
 * Class MassExport
 * @package SteadLane\MassExport
 */
class MassExport extends LeftAndMain implements PermissionProvider
{
    private static $url_segment = 'mass-export';
    private static $menu_title = 'Mass Export';
    private static $menu_icon_class = 'font-icon-database';
    private static $menu_priority = -1;

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
        Requirements::css('steadlane/silverstripe-massexport:client/dist/styles/massexport.css');
        Requirements::javascript('steadlane/silverstripe-massexport:client/dist/js/massexport.js');
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
        $this->CleanUpOldFiles();

        $vars = $this->request->postVars();

        // @TODO: allow for empty date fields
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

        $zip = new \ZipArchive();
        $zipFilename = $this->getMassExportDirectory()."/MassExport_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.zip';

        if (file_exists($zipFilename)) {
            @unlink($zipFilename);
        }

        $fileList=[];
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

            $filename = $this->getMassExportDirectory()."/{$modelName}_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.csv';

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
            array_push($fileList, $filename);
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

            $filename = $this->getMassExportDirectory()."/UDF_{$title}_" . date('Ymd', strtotime($vars['exportFrom'])) . '-' . date('Ymd', strtotime($vars['exportTo'])) . '.csv';

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
            array_push($fileList, $filename);
            $fileCount++;
        }

        $zip->close();

        // Clean up
        foreach($fileList as $fn) {
            if (file_exists($fn)) {
                @unlink($fn);
            }
        }

        if (!$fileCount) {
            if (file_exists($zipFilename)) {
                unlink($zipFilename);
            }
            $form->sessionMessage('No records were found within that date range. Export not available.', 'bad');
            return $this->redirectBack();
        }

        if (empty($zipFilename) || !file_exists($zipFilename)) {
            $form->sessionMessage('Error while building zip file.', 'bad');
            return $this->redirectBack();
        }

        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.basename($zipFilename));
        header('Content-Length: ' . filesize($zipFilename));
        readfile($zipFilename);
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

    protected function getMassExportDirectory()
    {
        // @TODO: allow changing directory using config
        $dir=__DIR__ . "/../exports";
        $dir=str_replace('\\', '/', $dir);
        return $dir;
    }

    protected function CleanUpOldFiles()
    {
        $maxAge=(1 * 86400); // 1 day
        $dir=$this->getMassExportDirectory();
        $h=opendir($dir);
        if ($h) {
            while (($fn=readdir($h))) {
                if ($fn=='..' || $fn=='.') { continue; }
                $ext=strtolower( substr($fn, strrpos($fn, '.')+1) );
                if ($ext=='zip' || $ext=='csv') {
                    $tm=@filemtime($dir.'/'.$fn);
                    if ($tm) {
                        $age=time()-$tm;
                        if ($age>$maxAge) {
                            @unlink($dir.'/'.$fn);
                        }
                    }
                }
            }
            closedir($h);
        }
    }
}
