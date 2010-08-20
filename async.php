<?php
    if (!function_exists('curl_multi_init'))
        throw(new Exception(
            "Multicurl extension is required for TwilioRestClientAsync to work"));

    include './twilio.php';

    class TwilioRestClientAsync extends TwilioRestClient {
        protected $mc;
        protected $IsSynchronous = false;       
        
        public function __construct($accountSid, $authToken,
            $endpoint = "https://api.twilio.com") {
            $this->mc = TwilioRestRequestAsync::getInstance();
            parent::__construct($accountSid, $authToken, $endpoint);
        }
    }


    class TwilioRestRequestAsync {
        const timeout = 3;
        static $inst = null;
        static $singleton = 0;
        private $mc;
        private $msgs;
        private $running;
        private $execStatus;
        private $selectStatus;
        private $sleepIncrement = 1.1;
        private $requests = array();
        private $responses = array();
        private $properties = array();

        function __construct() {
            if(self::$singleton == 0) {
                throw new Exception('This class cannot be instantiated by the new keyword.    You must instantiate it using: $obj =  TwilioRestResponseAsync::getInstance();');
            }

            $this->mc = curl_multi_init();
            $this->properties = array(
                'code'    => CURLINFO_HTTP_CODE,
                'time'    => CURLINFO_TOTAL_TIME,
                'length'=> CURLINFO_CONTENT_LENGTH_DOWNLOAD,
                'type'    => CURLINFO_CONTENT_TYPE,
                'url'     => CURLINFO_EFFECTIVE_URL
                );
        }

        public function addCurl($ch) {
            $key = $this->getKey($ch);
            $this->requests[$key] = $ch;
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));

            $code = curl_multi_add_handle($this->mc, $ch);
            
            // (1)
            if($code === CURLM_OK || $code === CURLM_CALL_MULTI_PERFORM) {
                do {
                        $code = $this->execStatus = curl_multi_exec($this->mc, $this->running);
                } while ($this->execStatus === CURLM_CALL_MULTI_PERFORM);

                
                return new TwilioRestResponseAsync($key);
            } else {
                return $code;
            }
        }

        public function getResult($key = null) {
            if($key != null) {
                if(isset($this->responses[$key])) {
                    return $this->responses[$key];
                }

                $innerSleepInt = $outerSleepInt = 1;
                while($this->running && ($this->execStatus == CURLM_OK || $this->execStatus == CURLM_CALL_MULTI_PERFORM)) {
                    usleep($outerSleepInt);
                    $outerSleepInt = max(1, ($outerSleepInt*$this->sleepIncrement));
                    $ms=curl_multi_select($this->mc, 0);
                    if($ms > 0) {
                        do{
                            $this->execStatus = curl_multi_exec($this->mc, $this->running);
                            usleep($innerSleepInt);
                            $innerSleepInt = max(1, ($innerSleepInt*$this->sleepIncrement));
                        }while($this->execStatus==CURLM_CALL_MULTI_PERFORM);
                        $innerSleepInt = 1;
                    }
                    $this->storeResponses();
                    if(isset($this->responses[$key]['data'])) {
                        return $this->responses[$key];
                    }
                    $runningCurrent = $this->running;
                }
                return null;
            }
            return false;
        }

        private function getKey($ch) {
            return (string)$ch;
        }

        private function headerCallback($ch, $header) {
            $_header = trim($header);
            $colonPos= strpos($_header, ':');
            if($colonPos > 0) {
                $key = substr($_header, 0, $colonPos);
                $val = preg_replace('/^\W+/','',substr($_header, $colonPos));
                $this->responses[$this->getKey($ch)]['headers'][$key] = $val;
            }
            return strlen($header);
        }

        private function storeResponses() {
            while($done = curl_multi_info_read($this->mc)) {
                $key = (string)$done['handle'];
                $this->responses[$key]['data'] = curl_multi_getcontent($done['handle']);
                foreach($this->properties as $name => $const) {
                    $this->responses[$key][$name] = curl_getinfo($done['handle'], $const);
                }
                curl_multi_remove_handle($this->mc, $done['handle']);
                curl_close($done['handle']);
            }
        }

        static function getInstance() {
            if(self::$inst == null) {
                self::$singleton = 1;
                self::$inst = new TwilioRestRequestAsync();
            }

            return self::$inst;
        }
    }

    class TwilioRestResponseAsync implements Countable, IteratorAggregate {
        private $key;
        private $requests;
        private $response;
        private $members;

        public function __construct($key) {
            $this->key = $key;
            $this->requests = TwilioRestRequestAsync::getInstance();
        }

        public function __get($name) {
            $this->getResponse();
            return isset($this->$name) ? $this->$name : null;
        }

        public function __isset($name) {
            $val = self::__get($name);
            return empty($val);
        }

        // Implementation of the IteratorAggregate::getIterator() to support foreach ($this as $...)
        public function getIterator () {
            $this->getResponse();
            return new ArrayIterator($this->response);
        }

        // Implementation of Countable::count() to support count($this)
        public function count () {
            $this->getResponse();
            return count($this->response);
        }
        
        private function getResponse() {
            if(!$this->response) {
                $response = $this->requests->getResult($this->key);
                $this->response = true;
                preg_match('/([^?]+)\??(.*)/', $response['url'], $matches);
                $this->Url = $matches[1];
                $this->QueryString = $matches[2];
                $this->ResponseText = $response['data'];
                $this->HttpStatus = $response['code'];
                if($this->HttpStatus != 204)
                    $this->ResponseXml = @simplexml_load_string($response['data']);
                
                if($this->IsError = ($this->HttpStatus >= 400))
                    $this->ErrorMessage =
                        (string)$this->ResponseXml->RestException->Message;
            }
        }
    }

    /*
     * Credits:
     *    - (1) Alistair pointed out that curl_multi_add_handle can return CURLM_CALL_MULTI_PERFORM on success.
     */
