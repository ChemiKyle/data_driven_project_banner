<?php

namespace ProjectBanner\ExternalModule;

use ExternalModules\AbstractExternalModule;
use REDCap;

class ExternalModule extends AbstractExternalModule {

    function redcap_every_page_top($project_id) {
        $url = $_SERVER['REQUEST_URI'];
        $is_on_project_home = preg_match("/\/redcap_v\d+\.\d+\.\d+\/index\.php\?pid=\d+\z/", $url);
        $is_on_project_setup = preg_match("/.*ProjectSetup.*/", $url);
        $criteria = $this->getSystemSetting('criteria');

        if ($this->getSystemSetting('display_everywhere') || ($is_on_project_home || $is_on_project_setup)) {
            $sql_response = $this->queryData();
            switch ($criteria) {
                case "custom":
                    if (!$this->queryCriteria()) return;
                    $this->displayBanner($sql_response);
                    break;
                case "require_result":
                    // check if the criteria returns anything
                    if (!$sql_response) return;
                case "always":
                    $this->displayBanner($sql_response);
                    break;
                default:
                    $this->displayBanner($sql_response);
                    break;
            }
        }
    }


    function displayBanner($sql_response) {
        echo "<style>
        #project-banner {" .
            "--bg-color: " . $this->getSystemSetting('bg_color') . ";" .
            "--border-color: " . $this->getSystemSetting('border_color') . ";" .
        "}
        </style>";

        $this->includeCss('css/banner.css');
        $this->includeJs('js/banner_inject.js');

        if ( !$banner_text = $this->getSystemSetting('banner_text')  )  {
            // Default banner text
            $banner_text = "This is the default project banner. Change this in the system level module configuration for the Data Driven Project Banner Module.</br>";
        }

        if ($sql_response) {
            $banner_text = $this->replaceSmartVariables($banner_text, $sql_response);
        }

        $banner_text = json_encode($banner_text);
        echo "<script type='text/javascript'>var data_driven_project_banner_text = $banner_text;</script>";
    }


    function replaceSmartVariables($input_text, $sql_response) {
        // "Data piping" recreation
        $smart_variables = (
            array_map(
                function($x) { return "[$x]"; },
                array_keys($sql_response[0])
            )
        );

        $smart_variables = array_combine($smart_variables, $sql_response[0]);
        $smart_variables["[project_title]"] = REDCap::getProjectTitle();

        return str_replace(
            array_keys($smart_variables),
            array_values($smart_variables),
            $input_text
        );
    }


    private function performPrebuiltQuery($prebuilt_sql) {
        if (!$prebuilt_sql) {
            return;
        }

        $sql = str_replace("[project_id]", PROJECT_ID, $prebuilt_sql);

        // TODO: migrate to framework v4's query in major version change
        if ($response = db_query($sql)) {
            return ($response->fetch_all(MYSQLI_ASSOC));
        }

        return false;
    }

    function queryCriteria() {
        return $this->performPrebuiltQuery($this->getSystemSetting('custom_criteria_sql'));
    }


    function queryData() {
        return $this->performPrebuiltQuery($this->getSystemSetting('data_sql'));
    }


    protected function includeCss($path) {
        echo '<link rel="stylesheet" href="' . $this->getUrl($path) . '">';
    }


    protected function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }


}
