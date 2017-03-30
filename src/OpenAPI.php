<?php
namespace Infoblast;

/**
 * openapi.php
 *
 * Library for infoblast (sms gateway) api
 *
 * @package		Infoblast
 * @author		Muhammad Hamizi Jaminan <hello@hamizi.net>
 */

class OpenAPI
{
	/**
	 *
	 * vars for current session id
	 *
	 * @access private
	 */
	private $loginSession = null;

	/**
	 * URL_*
	 *
	 * infoblast api endpoint url's constant
	 *
	 * @access private
	 */
	private const URL_LOGIN 	= 'http://www.infoblast.com.my/openapi/login.php';
	private const URL_LOGOUT 	= 'http://www.infoblast.com.my/openapi/logout.php';
	private const URL_LIST		= 'http://www.infoblast.com.my/openapi/getmsglist.php';
	private const URL_DETAIL 	= 'http://www.infoblast.com.my/openapi/getmsgdetail.php';
	private const URL_DELETE 	= 'http://www.infoblast.com.my/openapi/delmsg.php';
	private const URL_SEND 		= 'http://www.infoblast.com.my/openapi/sendmsg.php';
	private const URL_STATUS 	= 'http://www.infoblast.com.my/openapi/getsendstatus.php';

 	/**
	 * class constructor
	 *
	 * @access	public
	 */
	function __construct()
	{
		$this->loginSession = $this->makeAuth();
	}

	/**
	 * send sms to open api gateway
	 *
	 * @access public
	 * @param string|array $to
	 * @param string $message sms message
	 * @param string $type message type (default = text)
	 * @return string
	 */
	public function send($to, $message, $type = 'text')
	{
		if ( $this->loginSession === null )
			return null;

		if ( empty($to) )
			return 'Please enter phone number';

		if ( empty($message) )
			return 'Please enter sms message';

		$data = [
					'sessionid' => $this->loginSession,
					'to' => is_array($to) ? implode(',', $to) : $to,
					'message' = $message,
					'msgtype' => $type
				];

		$content = $this->request(self::URL_SEND, $data);

		$this->request(self::URL_LOGOUT, $tmp);

		return $this->xmlAttribute($content);
	}

	/**
	 * Pull / retrieve sms list from openapi server
	 *
	 * @access public
	 * @param string $status sms status (optional, default: new)
	 * @param bool $delete delete sms from server after fetch (optional, default: false)
	 * @return array
	 */
	public function pull($status = 'new', $delete = false)
	{
		if ( $this->loginSession === null )
			return null;

		$data = [
					'sessionid' => $this->loginSession,
					'status'] => $status
				];

		$content = $this->request(self::URL_LIST, $data);

		unset($data['status']);
		$records = [];

		$smsRecord = $this->buildList($content);

		if ( count($smsRecord) > 0 )
		{
			foreach( $smsRecord as $num )
			{
				$data['uid'] = $num;

				$content = $this->request(self::URL_DETAIL, $data);
				
				$dom = new DomDocument('1.0', 'utf-8');
				$dom->loadXML($content);
				$object = simplexml_import_dom($dom->documentElement);

				if ( is_object($object) )
				{
					$records[$num]['uid'] = (int) $num;
					$records[$num]['datetime'] = empty($object->msginfo->datetime) ? time() : (int) $object->msginfo->datetime;
					$records[$num]['from'] = (string) $object->msginfo->from;
					$records[$num]['to'] = (string) $object->msginfo->to;
					$records[$num]['subject'] = (string) $object->msginfo->subject;
					$records[$num]['msgtype'] = (string) $object->msginfo->msgtype;
					$records[$num]['message'] = (string) $object->msginfo->message;

					if ( $delete === true )
						$this->request(self::URL_DELETE, $data);
				}
			}
		}

		unset($data['uid']);
		$this->request(self::URL_LOGOUT, $data);

		return $records;
	}

	/**
	 * get sms send status to open api gateway
	 *
	 * @access public
	 * @param string $msgID
	 * @param bool $fullstatus
	 * @return string|array
	 */
	public function status($msgID, $fullstatus = false)
	{
		if ( $this->loginSession === null )
			return null;

		$session = ['sessionid' => $this->loginSession];
		$data = array_merge($session, ['msgid' => $msgID ]);
		
		$content = $this->request(self::URL_STATUS, $data);
		$this->request(self::URL_LOGOUT, $session);

		$object = @simplexml_load_string($content);
		$response = null;
		
		if ( is_object($object) )
		{
			if ( $fullstatus )
			{
				$response['msgid'] = (string) $object->stats->record->msgid;
				$response['datetime'] = (int) $object->stats->record->enddate;				
				$response['from'] = (string) $object->stats->record->aparty;
				$response['to'] = (string) $object->stats->record->bparty;
				$response['status'] = (string) $object->stats->record->status;
			}
			else
			{
				$response = (string) $object->stats->record->status;
			}
		}
		
		return $response;
	}

	/**
	 * makeAuth
	 *
	 * authenticate session to openapi
	 *
	 * @access private
	 * @return string
	 */
	private function makeAuth()
	{
		$data = [
					'username' => getenv('INFOBLAST_USERNAME', true) ?: getenv('INFOBLAST_USERNAME'),
					'password' => sha1(getenv('INFOBLAST_PASSWORD', true) ?: getenv('INFOBLAST_PASSWORD'))
				];
		
		$content = $this->request(self::URL_LOGIN, $data);
		$object = @simplexml_load_string($content);

		return ( is_object($object) && isset($object->sessionid) ) ? (string) $object->sessionid : null;
	}

	/**
	 * extract xml data for attributes name spaces
	 * and build data as array list
	 *
	 * @access private
	 * @param string $xmlContent
	 * @param string $attr (optional) default : uid
	 * @return array
	 */
	private function buildList($xmlContent, $attr = 'uid')
	{
		$object = @simplexml_load_string($xmlContent);
		$total = count($object);

		if ( $total > 0 )
		{
			for ( $i = 0; $i < $total; $i++ )
			{
				foreach($object->msginfo[$i]->attributes() as $key => $val)
				{
					if ( $key == $attr )
						$record[] = (int) $val;
				}
			}

			return $record;
		}

		else
			return null;
	}

	/**
	 *  request
	 *
	 * Fetching web using fopen function
	 *
	 * @access private
	 * @param string $url
	 * @param array $data (optional)
	 * @param string $optional_header (optional)
	 * @return string
	 */
	private function request($url, $data = null, $optional_header = null)
	{
		$param = array(
		                'http' => array(
										'method' => 'POST',
										'header' => "Content-type: application/x-www-form-urlencoded\r\n",
										'content' => http_build_query($data)
										)
		                );

		if ( $optional_header !== null )
			$param['http']['header'] = $optional_header;

		$context = stream_context_create($param);
		$handler = fopen($url, 'rb', false, $context);

		if ( ! $handler )
			throw new Exception('Unable to connect to ' . $url);

		$content = stream_get_contents($handler);

		if ( $content === false )
			throw new Exception('Unable to read data from ' . $url);

		return $content;
	}

	/**
	 * parse attribute from xml string
	 *
	 * @param string $xml XML content
	 * @return string
	 */
	private function xmlAttribute($xml, $attr = 'status')
	{
		$object = @simplexml_load_string($xml);
		$response = null;
		
		if ( is_object($object) )
		{
			foreach( $object->attributes() as $key => $val )
			{
				if ( $key == $attr )
					$status = (string) $val;
			}
			
			$response['messageid'] = (string) $object->messageid;
			$response['status'] =	$status = trim($status);
		}

		return $response;
	}
}