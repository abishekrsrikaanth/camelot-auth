<?php namespace T4s\CamelotAuth\Auth;

use T4s\CamelotAuth\Auth\Saml2\Saml2Auth;
use T4s\CamelotAuth\Auth\Saml2\Saml2Constants;

use T4s\CamelotAuth\Database\DatabaseInterface;
use T4s\CamelotAuth\Config\ConfigInterface;
use T4s\CamelotAuth\Session\SessionInterface;
use T4s\CamelotAuth\Cookie\CookieInterface;
use T4s\CamelotAuth\Messaging\MessagingInterface;
use T4s\CamelotAuth\Events\DispatcherInterface;

use T4s\CamelotAuth\Auth\Saml2\Messages\AuthRequestMessage;
use T4s\CamelotAuth\Auth\Saml2\Messages\ResponseMessage;

use T4s\CamelotAuth\Auth\Saml2\bindings\Binding;
use T4s\CamelotAuth\Auth\Saml2\bindings\HTTPRedirectBinding;

class Saml2SPAuth extends Saml2Auth implements AuthInterface
{


	public function __construct($provider,ConfigInterface $config,SessionInterface $session,CookieInterface $cookie,DatabaseInterface $database,MessagingInterface $messaging,$path)
	{
		parent::__construct($provider,$config,$session,$cookie,$database,$messaging,$path);
	}

	public function metadata()
	{

	}

	public function authenticate(array $credentials, $remember = false,$login = true)
	{

		
		// if user is logged in 
		if(!is_null($this->user()))
		{
			//redirect to dashboard
			return true;
		}
		// check if a idp entity id is set in the credentails
		if(isset($credentials['entityID']))
		{
			// override the provider
			$this->provider = $credentials['entityID'];
		}
		// check if the entity provider is valid
		if(!$this->metadataStore->isValidEnitity($this->provider))
		{
			$exception = 'T4s\CamelotAuth\Auth\Saml2\Exceptions\EntityNotFoundException';
			throw new $exception("EntityID (".$this->provider.") is not registered with this Service Provider");				
		}


		if(strpos($this->path,'AssertionConsumingService') !== false)
		{
			// handle assertion message
			return $this->handleAsertionConsumingServiceRequest();
		}
		elseif(strrpos($this->path, 'SingleLogoutService') !== false)
		{
			// handle logout message
			return $this->handleSignoutRequest();
		}
		
		return $this->sendAuthenticationRequest();
	}


	public function register(array $accountDetails = array())
	{

	}

	public function logout()
	{

	}




	protected function sendAuthenticationRequest()
	{
		// lets start by getting the idp metadata
		$idpMetadata = $this->metadataStore->getEntity($this->provider);
	
		// create a new AuthRequest and send it to a idp
		$authnMessage = new AuthRequestMessage($idpMetadata,$this->getMetadata());

		$authnMessage->setAssertionConsumingServiceURL($this->callbackUrl.'/AssertionConsumingService');
		// where should we redirect the user after a successfull login 
		//$request->setRelayState($this->getRelayState());

		$request = new HTTPRedirectBinding();

		$request->send($authnMessage);
	}

	protected function handleAsertionConsumingServiceRequest()
	{
		$binding = Binding::getBinding();

		// if its a artifact response then we need to have the keys so lets inject them here
		if($binding instanceof HTTPHTTPArtifactBinding)
		{
			$b->setSPMetadata($this->getMetadata());
		}
		// lets get the response message
		$response = $binding->receive();
		if(!($response instanceof ResponseMessage))
		{
			throw new \Exception("The Assertion Consuming Service has recieved an invalid message");
		}

		$this->provider = $response->getIssuer();
		if(is_null($this->provider))
		{
			throw new \Exception("the message recieved does not specify the issuer", 1);
			
		}


		var_dump($response->getNameId());

		var_dump($response->getAttributes());
	}
}