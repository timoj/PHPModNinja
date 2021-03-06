<?php

/**
 * Created by PhpStorm.
 * User: Timo
 * Date: 24/09/2017
 * Time: 00:32
 */
class ModNinja
{
    var $doTestModStates;
    var $overwriteBackupDirectory;
    var $modNamesInConfig;
    var $mods;
    var $modNamesInStates;
    var $states;

    var $logFile = "ModNinjaLog.log";

    function __construct()
    {
        $this->init();
    }

    private function init()
    {
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . '.phpmodninja')) {
            $this->loadModConfig();
            $this->loadModStates();

            $this->checkConfigToModStates();
            $this->checkStatesToConfig();
            $this->saveStates();
        } else {
            $this->initializeInstall();
        }
    }

    private function initializeInstall()
    {
        if (mkdir(dirname(__FILE__) . DIRECTORY_SEPARATOR . '.phpmodninja', 0777, true)) {
            file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . '.phpmodninja' . DIRECTORY_SEPARATOR . 'ModConfig.json', json_encode(array("testModStates" => false, "overwriteBackupDirectory" => "backup", "mods" => array())));
        }
    }

    private function loadModConfig()
    {
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . 'ModConfig.json')) {
            $str = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . 'ModConfig.json');
            $json = json_decode($str, true);

            $this->doTestModStates = $json['testModStates'];
            $this->overwriteBackupDirectory = $json['overwriteBackupDirectory'];
            $this->mods = $json['mods'];

            $modNamesInConfig = array();
            foreach ($this->mods as $mod) {
                array_push($modNamesInConfig, $mod['modName']);
            }
            $this->modNamesInConfig = $modNamesInConfig;
        } else {
            $this->addLogEntry("ERROR", "No config file found at '" . dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . 'ModConfig.json' . "'");
        }
    }

    private function loadModStates()
    {
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . 'ModStates.json')) {
            $str = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . 'ModStates.json');
            $json = json_decode($str, true);
            if ($json['states'] === null)
                $json['states'] = array();
            $this->states = $json['states'];

            $modNamesInStates = array();
            foreach ($this->states as $state) {
                array_push($modNamesInStates, $state['modName']);
            }
            $this->modNamesInStates = $modNamesInStates;
        } else {
            $this->states = array();
            $this->modNamesInStates = array();
        }
    }

    private function checkConfigToModStates()
    {
        foreach ($this->modNamesInConfig as $modNameInConfig) {
            if (!in_array($modNameInConfig, $this->modNamesInStates))
                $this->addNewMod($modNameInConfig);
        }
    }

    private function checkStatesToConfig()
    {
        foreach ($this->modNamesInStates as $modNameInStates) {
            if (!in_array($modNameInStates, $this->modNamesInConfig))
                $this->removeMod($modNameInStates);
        }
    }

    private function addNewMod($modName)
    {
        echo "here1";
        foreach ($this->mods as $mod) {
            if ($mod['modName'] === $modName) {
                if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToSource'])) {
                    if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToDeploy'])) {
                        if ($mod['overwriteFiles']) {
                            if ($this->backupFile(dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToDeploy'], $this->overwriteBackupDirectory . DIRECTORY_SEPARATOR . $mod['pathToDeploy'])) {
                                $this->moveFile(dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToSource'], dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToDeploy']);
                                $this->addNewModState($mod['modName'], $mod['pathToDeploy'], $this->overwriteBackupDirectory . DIRECTORY_SEPARATOR . $mod['pathToDeploy']);
                            }
                        } else {
                            $this->addLogEntry("ERROR", "Cannot install mod '" . $mod['modName'] . "', because there already exists a file on the given deployment path.");
                        }
                    } else {
                        $this->moveFile(dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToSource'], dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToDeploy']);
                        $this->addNewModState($mod['modName'], $mod['pathToDeploy']);
                    }
                } else {
                    $this->addLogEntry("ERROR", "Trying to install mod '" . $mod['modName'] . "', but source file does not exist at '" . dirname(__FILE__) . DIRECTORY_SEPARATOR . $mod['pathToSource'] . "'");
                }
            }
        }
    }

    private function removeMod($modName)
    {
        echo "here2";
        foreach ($this->states as $key => $state) {
            if ($state['modName'] === $modName) {
                if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $state->deployedAt)) {
                    unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . $state->deployedAt);
                    if ($state->originalAt) {
                        $this->moveFile(dirname(__FILE__) . DIRECTORY_SEPARATOR . $state->originalAt, dirname(__FILE__) . DIRECTORY_SEPARATOR . $state->deployedAt);
                    }
                } else {
                    $this->addLogEntry("WARNING", "Trying to uninstall mod '" . $state['modName'] . "', but there seems to be no mod deployed at the deployment path '" . $state->deployedAt . "'. Removing state.");
                }
                unset($this->states[$key]);
            }
        }
    }

    private function addNewModState($modName, $deploymentPath, $originalPath = false)
    {
        array_push($this->states, array(
            "modName" => $modName,
            "deployedAt" => $deploymentPath,
            "originalAt" => $originalPath
        ));
    }

    private function addLogEntry($severity, $message)
    {
        $fd = fopen(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . $this->logFile, "a");
        fwrite($fd, date("Y-m-d H:i:s") . "[ " . $severity . " ]" . $message . "\n");
        fclose($fd);
    }

    private function moveFile($a, $b)
    {
        if (!$this->copyWithDirCreate($a, $b)) {
            $this->addLogEntry("ERROR", "Cannot move '" . $a . " to '" . $b . "'");
        }
    }

    private function backupFile($a, $b)
    {
        if ($this->copyWithDirCreate($a, $b)) {
            unlink($a);
            return true;
        } else {
            $this->addLogEntry("ERROR", "Cannot backup original file '" . $a . " to '" . $b . "'");
        }
        return false;
    }

    private function saveStates()
    {
        file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . ".phpmodninja" . 'ModStates.json', json_encode(array(
            "states" => $this->states
        )));
    }

    private function copyWithDirCreate($a, $b)
    {
        $path = pathinfo($b);
        if (!file_exists($path['dirname'])) {
            mkdir($path['dirname'], 0777, true);
        }
        return copy($a, $b);
    }
}