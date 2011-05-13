<?php

	/**
 	* Request class
 	*
 	* Request class contents all the functions needed to initialise a request and send it
 	* 
 	* @author     Arthur GALLOUIN for NUXEO agallouin@nuxeo.com
 	*/
	class Request {
		
		private $finalRequest;
		private $url;
		private $headers;
		private $method;
		private $iterationNumber;
		private $HEADER_NX_SCHEMAS;
		private $blobList;
		private $X_NXVoidOperation;
		
		
		public function Request($url, $headers = "Content-Type:application/json+nxrequest", $requestId) {
			$this->url = $url . "/" . $requestId;
			$this->headers = $headers;
			$this->finalRequest = '{}';
			$this->method = 'POST';
			$this->iterationNumber = 0;
			$this->HEADER_NX_SCHEMAS = 'X-NXDocumentProperties:';
			$this->blobList = null;
			$this->X_NXVoidOperation = 'X-NXVoidOperation: true';
		}
		
		/**
		 * Set X-NXVoidOperation header
		 * 
		 * This header is used for the blob upload, it's noticing if the blob must be send back to the
		 * client. If not used, i might be great to not using it because it will save time and connection
		 * cappacity
		 *
		 * @param      $headerValue value taken by the header
		 * @author     Arthur GALLOUIN for NUXEO agallouin@nuxeo.com
		 */
		public function SetX_NXVoidOperation($headerValue = '*'){
			$this->X_NXVoidOperation = 'X-NXVoidOperation:'. $headerValue;
		}
		
		/**
 		* SetSchema function
 		*
 		* Set the schemas in order to obtain file properties
 		* 
 		* @param	  $schema : name the schema you want to obtain
 		* @author     Arthur GALLOUIN for NUXEO agallouin@nuxeo.com
 		*/
		public function SetSchema($schema = '*'){
			$this->headers = array($this->headers, $this->HEADER_NX_SCHEMAS . $schema);
			return $this;
		}
		
		/**
 		* Set function
 		*
 		* This function is used to load data in the request (such as input, context and params fields)
 		* 
 		* @param	  $requestType : contents name of the field
 		* 			  $requestContentOrVarName : contents the name of the var or the content of the field 
 		* 										 in the case of an input field
 		* 			  $requestVarVallue : vallue of the var define in $requestContentTypeOrVarName(if needed)
 		* @author     Arthur GALLOUIN for NUXEO agallouin@nuxeo.com
 		*/
		public function Set($requestType, $requestContentOrVarName, $requestVarVallue =  NULL){
			
			if ($requestVarVallue !== NULL){
				if ($this->iterationNumber === 0){
					$this->finalRequest = array(
		  				$requestType=> array( $requestContentOrVarName => $requestVarVallue)
		  			);
				}else if ($this->iterationNumber === 1) {
					$this->finalRequest[$requestType] = array($requestContentOrVarName => $requestVarVallue);
				}else if ($this->iterationNumber === 2){
					$this->finalRequest[$requestType][$requestContentOrVarName] = $requestVarVallue;
				}
					
				$this->iterationNumber = 2;
			}else{
				if ($this->iterationNumber === 0){
					$this->finalRequest = array(
		  				$requestType=> $requestContentOrVarName
		  			);
				}else{
					$this->finalRequest[$requestType] = $requestContentOrVarName;
				}
					
				if ($this->iterationNumber === 0)
					$this->iterationNumber = 1;
			}
			
  			return $this;
		}
		
		/**
 		* MultiPart function
 		* 
 		* This function is used to send a multipart request (blob + request) to Nuxeo EM, such as the
 		* AttachBlob request
 		* 
 		* @author     Arthur GALLOUIN for NUXEO agallouin@nuxeo.com
 		*/
		private function MultiPart(){
			
			if (sizeof($this->blobList) > 1 AND !isset($this->finalRequest['params']['xpath']))
				$this->finalRequest['params']['xpath'] = 'files:files';
			
			$this->finalRequest = json_encode($this->finalRequest);
			
			$this->finalRequest = str_replace('\/', '/', $this->finalRequest);
						
			$this->headers = array($this->headers, 'Content-ID: request');
			
			$requestheaders = 'Content-Type: application/json+nxrequest; charset=UTF-8'."\r\n".
							  'Content-Transfer-Encoding: 8bit'."\r\n".
							  'Content-ID: request'."\r\n".
							  'Content-Length:'.strlen($this->finalRequest)."\r\n"."\r\n";
			
			$value = sizeof($this->blobList);
							
			$boundary = '====Part=' . time() . '='.(int)rand(0, 1000000000). '===';
			
			$data = "--" . $boundary . "\r\n" .
	     			$requestheaders . 
	     			$this->finalRequest . "\r\n" ."\r\n";
			
			for ($cpt = 0; $cpt < $value; $cpt++){
				
				$data = $data . "--" . $boundary . "\r\n" ;
				
				$blobheaders = 'Content-Type:'.$this->blobList[$cpt][1]."\r\n".
						       'Content-ID: input'. "\r\n" .
				               'Content-Transfer-Encoding: binary'."\r\n" .
				       	       'Content-Disposition: attachment;filename='. $this->blobList[$cpt][0].
				       	       "\r\n" ."\r\n";
				
				$data = "\r\n". $data .
	                	$blobheaders.
	                	$this->blobList[$cpt][2] . "\r\n"."\r\n";
			}
			
			$data = $data ."--" . $boundary."--";
			
            $final = array('http'=> array(
            					'method' => 'POST',
            					'content' => $data));
            
			$final['http']['header'] = 'Accept: application/json+nxentity, */*'. "\r\n".
	                				   'Content-Type: multipart/related;boundary="'.$boundary.
	                				   '";type="application/json+nxrequest";start="request"'. 
	                				   "\r\n". $this->X_NXVoidOperation;
			
            $final = stream_context_create($final);
            
            $fp = @fopen($this->url, 'rb', false, $final);
            
            $answer = @stream_get_contents($fp);

			$answer = json_decode($answer, true);
			  
			return $answer;
    	}
    	/**
    	 * 
    	 * Function used to load a Blob.
    	 * Many blobs could be loaded, they will be store in a blob array
    	 * 
    	 * @param $adresse : contains the path of the file to load
    	 * @param $contentType : type of the blob content (default : 'application/binary')
    	 */
    	public function LoadBlob($adresse, $contentType  = 'application/binary'){
    		if(!$this->blobList){
    			$this->blobList = array();
    		}
    		$eadresse = explode("/", $adresse);
    		
    		$fp = fopen($adresse, "r");
    		
    		if (!$fp)
				echo 'error loading the file';

			$futurBlob = stream_get_contents($fp);
    		$this->blobList[] = array(end($eadresse), $contentType, print_r($futurBlob, true));
    		
    		return $this;
    	}
    	
    	/**
 		* SendRequest function
 		*
 		* This function is used to send any kind of request to Nuxeo EM
 		*
 		* @author     Arthur GALLOUIN for NUXEO agallouin@nuxeo.com
 		*/
		public function SendRequest(){
			if (!$this->blobList){
				
				$this->finalRequest = json_encode($this->finalRequest);
			
				$this->finalRequest = str_replace('\/', '/', $this->finalRequest);
				
				$params = array('http' => array(
									'method' => $this->method,
									'content' => $this->finalRequest
				));
				if ($this->headers !== null) {
					$params['http']['header'] = $this->headers;
				}
				
				$ctx = stream_context_create($params);
				
				$fp = @fopen($this->url, 'rb', false, $ctx);
				
				$answer = @stream_get_contents($fp);
				
				if (!isset($answer) OR $answer == false)
					echo 'Error Server';
				else{
					if (null == json_decode($answer, true)){
						$documents = $answer;
						file_put_contents("tempstream", $answer);
					}
					else{
						$answer = json_decode($answer, true);
						$documents = new Documents($answer);
					}
				}
				
				return $documents;
			}
			else
				$this->MultiPart();
		}
		
	}
	
	class Utilities{
		private $ini;
		
		public function DateConverterPhpToNuxeo($date){
			return date_format($date, 'Y-m-d');
		}
		
		public function DateConverterNuxeoToPhp($date){
			$newDate = explode('T', $date);
			$phpDate = new DateTime($newDate[0]);
			return $phpDate;
		}
		
		public function DateConverterInputToPhp($date){
			
			$edate = explode('/', $date);
			$day = $edate[2];
			$month = $edate[1];
			$year = $edate[0];

			if ($month > 0 AND $month < 12)
				if ($month%2 == 0)
					if ($day < 1 OR $day > 31){
						echo 'date not correct';
						exit;
					}
				elseif($month == 2)
					if (year%4 == 0)
						if ($day > 29 OR $day < 0){
							echo 'date not correct';
							exit;
						}
				else
					if ($day > 28 OR $day < 0){
						echo 'date not correct';
						exit;
					}
					else
						if ($day > 30 OR $day < 0){
							echo 'date not correct';
							exit;
						}

			$phpDate = new DateTime($year . '-' . $month . '-' . $day);
			
			return $phpDate;
		}
		
		/**
		 * 
		 * Function Used to get Data from Nuxeo, such as a blob. MUST BE PERSONALISED. (Or just move the 
		 * headers)
		 * 
		 * 
		 * @param $path path of the file
		 */
		function getFileContent($path = '/default-domain/workspaces/jkjkj/teezeareate.1304515647395') {
			
			$eurl = explode("/", $path);
			
			header("Content-type: text/plain");
	  		header("Content-Disposition: attachment; filename=".end($eurl).'.pdf');
			
			$client = new PhpAutomationClient('http://localhost:8080/nuxeo/site/automation');
		
			$session = $client->GetSession('Administrator','Administrator');
			
			$answer = $session->NewRequest("Chain.getDocContent")->Set('context', 'path' . $path)
					  ->SendRequest();
			
			if (!isset($answer) OR $answer == false)
				echo '$answer is not set';
			else{
				print_r($answer);
			}
		}
	}
	
?>