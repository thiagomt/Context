<?php
/**
* This script reads translation files and fixes them by:
* - adding missing keys (basing on english translation)
* - cleaning up file formatting
* - checking for key duplicates
* - maintaining the same key order in all translation files
*
* REQUIRES PHP 5 >= 5.4 to run
*/

function json_readable_encode($in, $indent = 0) {
    $_myself = __FUNCTION__;
    $_escape = function($str) {
        return preg_replace("!([\b\t\n\r\f\"])!", "\\\\\\1", $str);
    };
    $indentWith = "    ";

    $out = '';

    foreach($in as $key=>$value) {
        $out .= str_repeat($indentWith, $indent + 1);
        $out .= "\"" . $_escape((string)$key)."\": ";

        if(is_object($value) || is_array($value)) {
            $out .= "\n";
            $out .= $_myself($value, $indent+1);
        } elseif(is_null($value)) {
            $out .= 'null';
        } elseif(is_string($value)) {
            $out .= "\"".$_escape($value) . "\"";
        } else {
            $out .= $value;
        }

        $out.= ",\n";
    }

    if(!empty($out)) {
        $out = substr($out, 0, -2);
    }

    $out = str_repeat($indentWith, $indent) . "{\n" . $out;
    $out .= "\n" . str_repeat($indentWith, $indent) . "}";

    return $out;
}

class Translation {
	private $filePath;
	private $messages;

	public function __construct($filePath) {
		if(!file_exists($filePath) || !is_readable($filePath)) {
			throw new Exception('File doesn\'t exist or is unreadable.');
		}

		$this->messages = array();
		$this->filePath = $filePath;
		$this->readJSON( json_decode( file_get_contents($filePath) ) );
	}

	public function readJSON($json) {
		if($json === NULL) {
			throw new Exception('JSON cannot be decoded.');
		}

		foreach($json as $key => $jsonObj) {
			$messageObj = array();
			$messageObj['message'] = $jsonObj->message;
			if(isset($jsonObj->description)) {
				$messageObj['description'] = $jsonObj->description;
			}

			if($this->hasMessage($key)) {
				throw new Exception('Duplicated translation key: ' . $key);
			}

			$this->messages[$key] = $messageObj;
		}
	}

	public function hasMessage($key) {
		return isset($this->messages[$key]) && !empty($this->messages[$key]['message']);
	}

	public function getMessage($key) {
		return isset($this->messages[$key]) ? $this->messages[$key] : NULL;
	}

	public function getMessages() {
		return $this->messages;
	}

	public function merge(Translation $baseTranslation) {
		$newMessages = array();
		$missing = 0;

		$baseMessages = $baseTranslation->getMessages();

		foreach($baseMessages as $key => $messageObj) {
			if($this->hasMessage($key)) {
				$newMessages[$key] = $this->getMessage($key);
			} else {
				$missing++;
				$newMessages[$key] = $messageObj;
			}
		}

		$this->messages = $newMessages;

		return $missing;
	}

	public function commit() {
		if(!is_writable($this->filePath)) {
			throw new Exception('File is not writable.');
		}

		$json = json_readable_encode($this->messages);

		if($json === FALSE) {
			throw new Exception('JSON cannot be encoded.');
		}

		if(file_put_contents($this->filePath, $json) === FALSE) {
			throw new Exception('Writing into file failed.');
		}
	}
}

$baseTranslationFolder = 'en';
$baseTranslation = new Translation($baseTranslationFolder . '/messages.json');

echo 'Number of messages: ' . count($baseTranslation->getMessages()) . PHP_EOL;

$baseTranslation->commit();

$currentFolder = new DirectoryIterator(dirname(__FILE__));

foreach ($currentFolder as $subfolder) {
	if($subfolder->isDot() || !$subfolder->isDir() || $subfolder->getFilename() == $baseTranslationFolder) {
		continue;
	}

	$translation = new Translation($subfolder->getPathname() . '/messages.json');
	$missingMessages = $translation->merge($baseTranslation);
	$translation->commit();

	echo 'Folder "' . $subfolder->getFilename() . '" has ' . $missingMessages . ' untranslated messages.' . PHP_EOL;
}