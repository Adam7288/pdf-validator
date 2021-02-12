<?php 
/*
Jan 2021 Adam Carpentieri
PDF validation and page count - checks for password protection
prerequisites: mupdf cli tools, qpdf 10+
*/

class PdfValidator {
	
	const BAD_PDF_STRINGS = [
		'not a PDF file' => 'corrupt or invalid file',
		'invalid password' => 'password protected'
	];
	
	private $path;
	private $timeout;
	private $validated;
	private $error = '';
	private $numPages;
	private $tmpFiles = [];
	private $timer;
	private $maxPages = 0; //0 means no check for max pages
	
	public function __construct($path, $timeout=60) {
		
		$this->path = $path;
		$this->timeout = $timeout;
		
		if(!file_exists($this->path)) {
			$this->validated = false;
			$this->error = "file does not exist";
		}
	}
	
	public function isValid() {
		
		if(!isset($this->validated)) 
			$this->validate();
		
		return $this->validated;
	}
	
	public function getNumPages() { 
		
		if(!isset($this->numPages)) {
			$numPages = trim(exec('timeout -k 5 ' . $this->getRemainingTime() . ' nice -n 19 qpdf --show-npages "' . $this->path . '"'));
			$this->numPages = is_numeric($numPages) && $numPages > 0 ? $numPages : 0;
		}
		
		return $this->numPages; 						  
	}
	
	public function setMaxPages($maxPages) {
		
		if(is_numeric($maxPages) && $maxPages > 0)
			$this->maxPages = $maxPages;
	}
	
	public function getRemainingTime() {
		
		if(!isset($this->timer))
			$this->timer = new Timer();
		
		$remainingTime = $this->timeout - $this->timer->getTotalTime();
			
		return $remainingTime < 0 ? 0 : $remainingTime;
	}
	
	public function getError() { return $this->error; }
	
	private function validate() {
		
		$this->validated = true;
		
		//qpdf check
		$output = [];
		$result = exec('timeout -k 5 ' . $this->getRemainingTime() . ' nice -n 19 qpdf --check "' . $this->path . '" 2>&1', $output);
		foreach($output as $line) {

			foreach(self::BAD_PDF_STRINGS as $searchString => $error) {

				if(stristr($line, $searchString) !== false) {

					$this->setInvalid($error);
					return;
				}
			}
		}

		//validate num pages
		if(($numPages = $this->getNumPages()) == 0) {
			$this->setInvalid('corrupt file (invalid number of pages)');
			return;
		}
		
		if($this->maxPages > 0 && $numPages > $this->maxPages) {
			$this->setInvalid('max pages exceeded');
			return;
		}

		//assign tmp file names
		$baseName = $this->newId();
		for($i=1; $i<=$this->getNumPages(); $i++) 
			$this->tmpFiles[] = '/tmp/' . $baseName . $i . ".png";

		//make sure mupdf outputs correct number of images **low resolution to speed up*
		exec('timeout -k 5 ' .  $this->getRemainingTime() . ' nice -n 19 sudo /usr/bin/mutool convert -O resolution=10 -o "/tmp/' . $baseName . '.png" "' . $this->path . '"'); 

		foreach($this->tmpFiles as $file) {

			clearstatcache(true, $file);

			if(!file_exists($file)) {

				$this->setInvalid('corrupt file');

				//error_log("qpdf output for corrupt file " . $this->path . ": " . print_r($output, true));

				return;
			}
		}
	}
	
	private function setInvalid($error) {
		
		$this->validated = false;
		$this->error = $error;
	}
	
	private function newId() { return substr(md5(uniqid(rand())),0,10);	}
	
	public function __destruct() {
		
		foreach($this->tmpFiles as $file) {
			
			clearstatcache(true, $file);
			
			if(file_exists($file))
				@unlink($file);
		}
	}
}

class Timer {
	
	private $startTime;
	private $lastCheckTime;
	private $intervals;
	const MICROSECONDS_IN_SECONDS = 1000000;
	
    public function __construct() {
        
		$this->startTime = $this->lastCheckTime = microtime(true);
		$intervals = array();
    }
	
	public function getTotalTime() { return $this->formatMicroTime(microtime(true) - $this->startTime); }
	
	public function getInterval($microTime=false) {
		
		$oldLastCheckTime = $this->lastCheckTime;
		$this->lastCheckTime = microtime(true);
		
		if($microTime)
			return $this->lastCheckTime - $oldLastCheckTime;
		else
			return $this->formatMicroTime($this->lastCheckTime - $oldLastCheckTime);	
	}
	
	public function registerInterval($intervalName, $period) { //$period is in seconds
		
		$this->intervals[$intervalName]['period'] = $period;
		$this->intervals[$intervalName]['lastCheckTime'] = microtime(true);
	}
	
	public function checkInterval($intervalName) {
		
		if(!isset($this->intervals[$intervalName]))
			return false;
		
		if((microtime(true) - $this->intervals[$intervalName]['lastCheckTime']) >= $this->intervals[$intervalName]['period']) {
			
			$this->intervals[$intervalName]['lastCheckTime'] = microtime(true);
			return true;
		}
		
		return false;
	}
	
	//microtime is a float -> convert to seconds
	private function formatMicroTime($microTime) { return number_format($microTime, 4); }
} 


?>