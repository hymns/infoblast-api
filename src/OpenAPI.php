<?php
namespace Hymns\Infoblast;

/**
 * openapi.php
 *
 * PHP packages for TM InfoBlast (SMS Gateway) API
 *
 * @package		Infoblast
 * @author		Muhammad Hamizi Jaminan <hello@hamizi.net>
 * @license		MIT
 */

class OpenAPI
{
	/**
	 *
	 * @var $session Session id for current session
	 */
	private $session = null;

	/**
	 * @var Error message
	 */
	public $error = '';

	/**
	 * URL_*
	 *
	 * infoblast api endpoint url's constant
	 *
	 * @access private
	 */
	private const URL_LOGIN 	= 'https://www.infoblast.com.my/openapi/login.php';
	private const URL_LOGOUT 	= 'https://www.infoblast.com.my/openapi/logout.php';
	private const URL_LIST		= 'https://www.infoblast.com.my/openapi/getmsglist.php';
	private const URL_DETAIL 	= 'https://www.infoblast.com.my/openapi/getmsgdetail.php';
	private const URL_DELETE 	= 'https://www.infoblast.com.my/openapi/delmsg.php';
	private const URL_SEND 		= 'https://www.infoblast.com.my/openapi/sendmsg.php';
	private const URL_STATUS 	= 'https://www.infoblast.com.my/openapi/getsendstatus.php';

 	/**
	 * class constructor
	 */
	public function __construct()
	{
		$this->session = $this->authenticate();
	}

	/**
	 * send sms to open api gateway
	 *
	 * @param string|array $to Recipient phone number.
	 * @param string $message The text message to be send
	 * @param string $type The type of essage (default = text)
	 * @return string
	 */
	public function send($to, $message, $type = 'text')
	{
		if ($this->session === null)
			return $this->error;

		if (empty($to))
			return 'Please enter phone number';

		if (empty($message))
			return 'Please enter sms message';

		$data = [
					'sessionid' => $this->session,
					'to' => is_array($to) ? implode(',', $to) : $to,
					'message' => $message,
					'msgtype' => $type
				];

		$payload = $this->request(self::URL_SEND, $data);

		$this->request(self::URL_LOGOUT);

		return $this->xmlAttribute($payload);
	}

	/**
	 * Pull / retrieve sms list from openapi server
	 *
	 * @access public
	 * @param string $status Type of SMS you want to fetch from server (optional, default: new)
	 * @param bool $delete Delete the SMS from server after fetch (optional, default: false)
	 * @return array
	 */
	public function pull($status = 'new', $delete = false)
	{
		if ($this->session === null)
			return $this->error;

		$data = [
					'sessionid' => $this->session,
					'status' => $status
				];

		$payload = $this->request(self::URL_LIST, $data);

		unset($data['status']);
		$messages = [];

		$smsRecord = $this->buildList($payload);

		if (count($smsRecord) > 0)
		{
			foreach($smsRecord as $num)
			{
				$data['uid'] = $num;

				$payload = $this->request(self::URL_DETAIL, $data);
								
				if (!$payload) break;

				$dom = new \DomDocument('1.0', 'utf-8');
				$dom->loadXML($payload);
				$object = simplexml_import_dom($dom->documentElement);

				if (is_object($object))
				{
					$messages[$num]['uid'] = (int) $num;
					$messages[$num]['datetime'] = empty($object->msginfo->datetime) ? time() : (int) $object->msginfo->datetime;
					$messages[$num]['from'] = (string) $object->msginfo->from;
					$messages[$num]['to'] = (string) $object->msginfo->to;
					$messages[$num]['subject'] = (string) $object->msginfo->subject;
					$messages[$num]['msgtype'] = (string) $object->msginfo->msgtype;
					$messages[$num]['message'] = (string) $object->msginfo->message;

					if ($delete === true)
						$this->request(self::URL_DELETE, $data);
				}
			}
		}

		unset($data['uid']);
		$this->request(self::URL_LOGOUT, $data);

		return $messages;
	}

	/**
	 * Get sms send status to open api gateway
	 *
	 * @param string $msgID The message ID that you want to retrieved the sending status
	 * @param bool $fullstatus Require full status? Pass True value
	 * @return string|array
	 */
	public function status($msgID, $fullstatus = false)
	{
		if ($this->session === null)
			return $this->error;

		$session = ['sessionid' => $this->session];
		$data = array_merge($session, ['msgid' => $msgID ]);
		
		$payload = $this->request(self::URL_STATUS, $data);
		$this->request(self::URL_LOGOUT, $session);

		$object = @simplexml_load_string($payload);
		$response = null;
		
		if (is_object($object))
		{
			if ($fullstatus)
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
	 * Authenticate session to openapi
	 *
	 * @return string
	 */
	public function authenticate()
	{
		$username = getenv('INFOBLAST_USERNAME', true) ?: getenv('INFOBLAST_USERNAME');
		$password = sha1(getenv('INFOBLAST_PASSWORD', true) ?: sha1(getenv('INFOBLAST_PASSWORD')));

		if ($username === false)
		{
			$this->error = 'Please setup infoblast username and password on your server enviroment. Please refer readme on this package.';
			return null;
		}

		$content = $this->request(self::URL_LOGIN, ['username' => $username, 'password' => $password]);
		$object = @simplexml_load_string($content);

		if ((is_object($object) && isset($object->sessionid)))
		{
			return (string) $object->sessionid;
		}
		else
		{
			$this->error = 'Invalid username or password. Please make sure your infoblast account are enable for using API.';
			return null;
		}
	}

	/**
	 * extract xml data for attributes name spaces
	 * and build data as array list
	 *
	 * @param string $xmlContent The payload from server response
	 * @param string $attr (optional) default : uid
	 * @return array
	 */
	private function buildList($xmlContent, $attr = 'uid')
	{
		$object = @simplexml_load_string($xmlContent);
		$total = count($object);

		if ($total > 0)
		{
			for ($i = 0; $i < $total; $i++)
			{
				foreach($object->msginfo[$i]->attributes() as $key => $val)
				{
					if ($key == $attr)
						$record[] = (int) $val;
				}
			}

			return $record;
		}

		else
			return null;
	}

	/**
	 * Make a request call to the server
	 *
	 * @param string $url The Infoblast API endpoint URL
	 * @param array $data Set of array for query parameters (optional)
	 * @param string $optional_header HTTP header payload (optional)
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

		if ($optional_header !== null)
			$param['http']['header'] = $optional_header;

		$context = stream_context_create($param);
		$handler = fopen($url, 'rb', false, $context);

		if (!$handler)
			throw new \Exception('Unable to connect to ' . $url);

		$content = stream_get_contents($handler);

		if ($content === false)
			throw new \Exception('Unable to read data from ' . $url);

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
		
		if (is_object($object))
		{
			foreach($object->attributes() as $key => $val)
			{
				if ($key == $attr)
					$status = (string) $val;
			}
			
			$response['messageid'] = (string) $object->messageid;
			$response['status'] =	$status = trim($status);
		}

		return $response;
	}
}