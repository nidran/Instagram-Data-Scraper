<?php
// Added multi cURL Class
require_once 'ParallelCurl.php';
/**
 * Instagram Scrapper Class
 */
class Instagram
{
    /**
     * set base url profile link
     * @var string
     */
    public $base_url = "https://www.instagram.com";
    /**
     * set profile link url
     * @var string
     */
    public $profile_base_url = "";
    /**
     * get all links array
     * @var array
     */
    public $link_array = array();
    /**
     * collection of data
     * @var array
     */
    public $data      = array();
    public $data_html = null;
    /**
     * JSON or ARRAY
     * Default is JSON to build awesome view, if you set ARRAY you have to make your own
     * JavaScript template by returning response as array
     * @var string
     */
    public $result_type = 'JSON';
    /**
     * [$error description]
     * @var string
     */
    public $error = 'window._sharedData = {"error": "{{error}}"};';
    /**
     * [__construct description]
     * @param array $url [description]
     */
    public function __construct()
    {
        // array to php vars
        extract($_POST);
        // var url
        $url = array();
        // var flag validation
        $validation = false;
        // validation
        if (isset($_POST['iUrl']) && is_array($_POST['iUrl'])) {
            // if array has a valid urls
            $validation = filter_var_array($_POST['iUrl'], FILTER_VALIDATE_URL);
            // array
            $url = $iUrl;
        } else {
            // if a valid url
            $validation = filter_input(INPUT_POST, 'iUrl', FILTER_VALIDATE_URL);
            // create array
            $url = array($iUrl);
        }
        // if data set & url
        if ($validation && is_array($url) && count($url) > 0) {
            // collect all urls
            $this->link_array = $url;
        } else {
            // show error message
            return $this->error_trace("Invalid or corrupt Instagram url request.");
        }
    }
    /**
     * [fetch_data description]
     * @return [type] [description]
     */
    public function fetch_data()
    {
        if (!is_array($this->link_array)) {
            return $this->error_trace("Invalid data request or bad input send...");
        } else {
            foreach ($this->link_array as $index => $link) {
                $this->insta_connect($link);
            }
            return $this->load_view();
        }
    }
    /**
     * Display the data
     * @return [type] [description]
     */
    public function load_view()
    {
        // set json header
        //header('Content-Type: application/javascript');
        // get script
        return $this->data;
    }
    public function get_curl_std_options()
    {
        $sppof_ip = "" . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255);
        // add additional curl options here
        $std_options = array(
            // return web page
            CURLOPT_RETURNTRANSFER => true,
            // don't return headers
            CURLOPT_HEADER         => false,
            // follow redirects
            CURLOPT_FOLLOWLOCATION => true,
            // handle all encodings
            CURLOPT_ENCODING       => "",
            // set referer on redirect
            CURLOPT_AUTOREFERER    => true,
            // timeout on connect
            CURLOPT_CONNECTTIMEOUT => 120,
            // timeout on response
            CURLOPT_TIMEOUT        => 120,
            // stop after 10 redirects
            CURLOPT_MAXREDIRS      => 10,
            // Disabled SSL Cert checks
            CURLOPT_SSL_VERIFYPEER => false,
            // user agent be present in the request
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
            // set fake ip address
            CURLOPT_HTTPHEADER     => array("REMOTE_ADDR: $sppof_ip", "HTTP_X_FORWARDED_FOR: $sppof_ip")
        );
        return $std_options;
    }
    /**
     * [insta_connect description]
     * @param [type] $link [description]
     */
    public function insta_connect($link, $loop = false)
    {
        $source = null;
        // get script json data
        $first_set_pattern = "/<script[^>](.*)_sharedData(.*)<\/script>/";
        // replacement string
        $get_json_data_pattern = '/(?i)<script[[:space:]]+type="text\/javascript"(.*?)>([^\a]+?)<\/script>/si';
        // check allow_url_fopen settings
        if (ini_get('allow_url_fopen') && extension_loaded('openssl')) {
            // get source html data
            $source = @file_get_contents($link);
        }
        // make sure cURL enable
        elseif (function_exists('curl_version')) {
            // execute multi curl
            $parallelcurl = new ParallelCurl(10);
            // set curl option
            $parallelcurl->setOptions($this->get_curl_std_options());
            // send link and get response by callback
            $parallelcurl->startRequest($link, array($this, 'get_request_info'));
            // get response
            $parallelcurl->finishAllRequests();
            // store html scrap data in class property
            $source = $this->data_html;
        } else {
            // show error
            $this->error_trace("You must enable Curl or allow_url_fopen & openssl to use this application");
        }
        // if source get
        if ($source) {
            // filter script tag
            preg_match_all($first_set_pattern, $source, $matches);
            // get script inside json data
            preg_match($get_json_data_pattern, array_shift($matches[0]), $array_string);
            // check result request type
            if ($this->result_type === 'ARRAY') {
                // convert javscript code to php assoc array object
                $js2php_array = json_decode(preg_replace('/(window\.\_sharedData \=|\;)/', '', $array_string[2]), true);
                // build result array, you have to manage html view by array
                $this->data = $this->build_useful_array($js2php_array);
            }
            // check if no error or 404 page
            else if (strpos($array_string[2], '"entry_data": {}') !== false) {
                // return error
                return $this->error_trace("Invalid or corrupt Instagram url: " . $link);
            } else {
                // return script directly in response
                $this->data = $array_string[2];
            }
        } else {
            return $this->error_trace("Invalid or corrupt Instagram url: " . $link);
        }
    }
    /**
     * [get_request_info description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function get_request_info($data)
    {
        return $this->data_html = $data;
    }
    /**
     * Build the array data from json respond
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function build_useful_array($data)
    {
        // pre($data);
        $collection = array();
        // collect all personal information, same key as in result data
        $collection['country_code']  = $data['country_code'];
        $collection['language_code'] = $data['language_code'];
        // get
        extract(array_shift($data['entry_data']['ProfilePage']));
        // this is total post count
        $collection['count']              = $user['media']['count'];
        $collection['biography']          = $user['biography'];
        $collection['external_url']       = $user['external_url'];
        $collection['count']              = $user['followed_by']['count'];
        $collection['count']              = $user['follows']['count'];
        $collection['follows_viewer']     = $user['follows_viewer'];
        $collection['full_name']          = $user['full_name'];
        $collection['id']                 = $user['id'];
        $collection['username']           = $user['username'];
        $collection['external_url']       = $user['external_url'];
        $collection['profile_pic_url']    = $user['profile_pic_url'];
        $collection['followed_by_viewer'] = $user['followed_by_viewer'];
        // collect all post information
        if ($user['media']['nodes']) {
            // parse one by one
            foreach ($user['media']['nodes'] as $index => $array) {
                $collection['post']['data'][$index]['id']            = $array['id'];
                $collection['post']['data'][$index]['thumbnail_src'] = $array['thumbnail_src'];
                $collection['post']['data'][$index]['is_video']      = $array['is_video'];
                $collection['post']['data'][$index]['date']          = $array['date'];
                $collection['post']['data'][$index]['display_src']   = $array['display_src'];
                $collection['post']['data'][$index]['caption']       = isset($array['caption']) ? $array['caption'] : '';
                $collection['post']['data'][$index]['comments']      = $array['comments']['count'];
                $collection['post']['data'][$index]['likes']         = $array['likes']['count'];
            }
        }
        return $collection;
    }
    /**
     * [error_trace description]
     * @param  string $error_message  [description]
     * @return [type] [description]
     */
    public function error_trace($error_message = '')
    {
        return $this->data = str_replace('{{error}}', $error_message, $this->error);
    }
}
