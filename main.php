<?php
//Your Variables go here: $GLOBALS['settings']['YourVariableName'] = YourVariableValue
class settings{
    public static function command($line):void{
        $lines = explode(" ",$line);
        if($lines[0] === "backup"){
            self::backup();
        }
        elseif($lines[0] === "reload"){
            self::reload();
        }
        elseif($lines[0] === "list"){
            echo json_encode($GLOBALS['settings'],JSON_PRETTY_PRINT) . "\n";
        }
        elseif($lines[0] === "show"){
            if(isset($lines[1])){
                $settingName = $lines[1];
                $settings = json_decode(json_encode($GLOBALS['settings']),true);

                $invalidChars = array("'",';',':','\\','(',')');
                foreach($invalidChars as $invalidChar){
                    if(strpos($settingName,$invalidChar) !== false){
                        mklog('warning','$settingName contained an invalid character: ' . $invalidChar,false);
                        goto theend;
                    }
                }

                $settingNames = array();
                if(strpos($settingName,"/") !== false){
                    $settingNames = explode("/",$settingName);
                }
                else{
                    $settingNames[0] = $settingName;
                }

                $settingCodeString = '';
                foreach($settingNames as $settingNamePart){
                    $settingCodeString .= "['" . $settingNamePart . "']";
                }

                $settingIsset = eval('return isset($settings' . $settingCodeString . ');');

                if($settingIsset){
                    $settingValue = eval('return $settings' . $settingCodeString . ';');
                    if(is_array($settingValue)){
                        echo json_encode($settingValue,JSON_PRETTY_PRINT);
                    }
                    elseif(is_bool($settingValue)){
                        echo boolean_to_string($settingValue);
                    }
                    else{
                        echo $settingValue;
                    }
                }
                else{
                    echo "Setting not set";
                }
                echo "\n";
                theend:
            }
        }
    }//Run when base command is class name, $line is anything after base command (string). e.g. > [base command] [$line]
    public static function init():void{
        //Set settings file location
        $settingsFile = 'settings.json';
        //Check if settings file does not exist
        if(!is_file($settingsFile)){
            mklog('general','Global settings file does not exist, creating file',false);
            //Create file pointer
            $settingsFilePointer = fopen($settingsFile,'w');
            //Check if file was not created
            if($settingsFilePointer === false){
                mklog('error','Unable to create global settings file, cannot continue (E001)',false);
            }
            //Write setting to global settings file, error on failure
            if(fwrite($settingsFilePointer,json_encode(array(),JSON_PRETTY_PRINT)) === false){
                mklog('error','Unable to add settings to global settings file, cannot continue (E002)',false);
            }
            //Close global settings file and check for error
            if(fclose($settingsFilePointer) === false){
                mklog('error','Unable to save global settings file, cannot continue (E003)',false);
            }
        }
        //Read settings file contents
        mklog('general','Reading global settings file');
        $settingsFileContents = file_get_contents($settingsFile);
        //Check if file contents are valid
        if($settingsFileContents === false){
            mklog('error','Unable to read from global settings file, cannot continue (E004)',false);
        }
        //Decode json data into settings array
        $GLOBALS['settings'] = json_decode($settingsFileContents);
    }
    public static function isset(string $settingName):bool{
        $settings = self::readFile();
        if($settings === false){
            return false;
        }

        $trace = debug_backtrace();
        if(isset($trace[1]['class'])){
            $settingName = $trace[1]['class'] . "/" . $settingName;
        }

        $settingCodeString = self::settingEvalString($settingName);
        if($settingCodeString === false){
            return false;
        }

        return eval('return isset($settings' . $settingCodeString . ');');
    }
    public static function read(string $settingName):mixed{
        $settings = self::readFile();
        if($settings === false){
            return false;
        }

        $trace = debug_backtrace();
        if(isset($trace[1]['class'])){
            $settingName = $trace[1]['class'] . "/" . $settingName;
        }

        $settingCodeString = self::settingEvalString($settingName);
        if($settingCodeString === false){
            return false;
        }

        $settingIsset = eval('return isset($settings' . $settingCodeString . ');');
        if($settingIsset){
            return eval('return $settings' . $settingCodeString . ';');
        }

        return false;
    }
    public static function set(string $settingName, mixed $settingValue, bool $overwrite=false):bool{
        $settings = self::readFile();
        if($settings === false){
            return false;
        }

        $trace = debug_backtrace();
        if(isset($trace[1]['class'])){
            $settingName = $trace[1]['class'] . "/" . $settingName;
        }

        $settingCodeString = self::settingEvalString($settingName);
        if($settingCodeString === false){
            return false;
        }

        $settingIsset = eval('return isset($settings' . $settingCodeString . ');');
        $writeSetting = false;
        if($settingIsset){
            if($overwrite){
                $writeSetting = true;
            }
        }
        else{
            $writeSetting = true;
        }

        $successful = false;
    
        if($writeSetting){
    
            $evalErr = false;
    
            eval('
                try{
                    unset($settings' . $settingCodeString . ');
                    $settings' . $settingCodeString . ' = $settingValue;
                }
                catch(\Error){
                    mklog("warning","Unable to set setting in non-array",false);
                    $evalErr = true;
                }
            ');
    
            if($evalErr){
                goto end;
            }

            json::writeFile('settings.json',$settings,true);
    
            $successful = true;
        }
    
        end:
        return $successful;
    }
    public static function unset(string $settingName):bool{
        $settings = self::readFile();
        if($settings === false){
            return false;
        }

        $trace = debug_backtrace();
        if(isset($trace[1]['class'])){
            $settingName = $trace[1]['class'] . "/" . $settingName;
        }

        $settingCodeString = self::settingEvalString($settingName);
        if($settingCodeString === false){
            return false;
        }

        //
    
        $evalErr = false;

        eval('
            try{
                unset($settings' . $settingCodeString . ');
            }
            catch(\Error){
                mklog("warning","Unable to set setting in non-array",false);
                $evalErr = true;
            }
        ');

        if($evalErr){
            goto end;
        }

        json::writeFile('settings.json',$settings,true);

        $successful = true;
    
        end:
        return $successful;
    }
    public static function backup(){
        files::copyFile("settings.json","backups/settings-" . time::stamp() . ".json");
        mklog('general','Created backup of settings file',false);
    }
    private static function settingEvalString(string $settingName):string|false{
        $invalidChars = array("'",';',':','\\','(',')');
        foreach($invalidChars as $invalidChar){
            if(strpos($settingName,$invalidChar) !== false){
                mklog('warning','$settingName contained an invalid character: ' . $invalidChar,false);
                return false;
            }
        }

        $settingNames = array();
        if(strpos($settingName,"/") !== false){
            $settingNames = explode("/",$settingName);
        }
        else{
            $settingNames[0] = $settingName;
        }

        $settingCodeString = '';
        foreach($settingNames as $settingNamePart){
            $settingCodeString .= "['" . $settingNamePart . "']";
        }

        return $settingCodeString;
    }
    private static function readFile():mixed{
        return json::readFile('settings.json',false);
    }
}