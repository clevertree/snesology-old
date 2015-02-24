<?php
/**
 * Created by PhpStorm.
 * User: ari
 * Date: 9/17/14
 * Time: 8:14 AM
 */
namespace Site\Account;

use CPath\Request\Executable\ExecutableRenderer;
use CPath\Request\Validation\Exceptions\ValidationException;
use Site\Account\DB\AccountEntry;
use CPath\Build\IBuildable;
use CPath\Build\IBuildRequest;
use CPath\Render\HTML\Attribute\Attributes;
use CPath\Render\HTML\Element\Form\HTMLButton;
use CPath\Render\HTML\Element\Form\HTMLFileInputField;
use CPath\Render\HTML\Element\Form\HTMLForm;
use CPath\Render\HTML\Element\Form\HTMLInputField;
use CPath\Render\HTML\Element\Form\HTMLPasswordField;
use CPath\Render\HTML\Element\Form\HTMLTextAreaField;
use CPath\Render\HTML\Element\HTMLElement;
use CPath\Render\HTML\Header\HTMLHeaderScript;
use CPath\Render\HTML\Header\HTMLHeaderStyleSheet;
use CPath\Render\HTML\Header\HTMLMetaTag;
use CPath\Request\Executable\IExecutable;
use CPath\Request\Form\IFormRequest;
use CPath\Request\IRequest;
use CPath\Request\Session\ISessionRequest;
use CPath\Request\Validation\RequiredValidation;
use CPath\Request\Validation\UserNameOrEmailValidation;
use CPath\Request\Validation\ValidationCallback;
use CPath\Response\Common\RedirectResponse;
use CPath\Route\IRoutable;
use CPath\Route\RouteBuilder;
use Site\Config;
use Site\PGP\Commands\PGPSearchCommand;
use Site\PGP\Exceptions\PGPKeyAlreadyImported;
use Site\PGP\PublicKey;
use Site\SiteMap;

class Register implements IExecutable, IBuildable, IRoutable
{
	const CLS_FIELDSET_TOOLS = 'fieldset-tools';
	const CLS_FORM = 'form-register';

    const FORM_ACTION = '/register/';
	const FORM_NAME = 'form-register';

	const PARAM_PUBLIC_KEY = 'public_key';
	const PARAM_RESET = 'reset';
	const PARAM_GENERATE = 'gen-keys';
	const PARAM_USER = 'gen-user';
	const PARAM_EMAIL = 'gen-email';
	const PARAM_PASSPHRASE = 'passphrase';
	const PARAM_SUBMIT = 'submit';
	const PARAM_LOAD_FILE = 'load-file';
	const PARAM_LOAD_STORAGE = 'load-storage';

//	const REGISTRATION_LIMIT = 86400;

	const PLACEHOLDER = "-----BEGIN PGP PUBLIC KEY BLOCK-----
Version: GnuPG v1

...
...
...
-----END PGP PUBLIC KEY BLOCK-----";

	private $mNewAccountFingerprint = null;

	private static $mTestMode = false;

	public function getRequestPath() {
		return self::FORM_ACTION;
	}

	/**
	 * Execute a command and return a response. Does not render
	 * @param IRequest $Request the request to execute
	 * @throws \CPath\Request\Exceptions\RequestException
	 * @throws \Exception
	 * @return HTMLForm
	 */
    function execute(IRequest $Request) {
        $inviteeEmail = null;
        $inviterFingerprint = null;
        $isInvite = false;
        if($Request instanceof ISessionRequest) {
            if(Invite::hasInviteContent($Request)) {
                $isInvite = true;
                list($inviteeEmail, $inviterFingerprint) = Invite::getInviteContent($Request);
            }
        }

	    $Form = new HTMLForm('POST', self::FORM_ACTION, self::FORM_NAME, self::CLS_FORM,
		    new HTMLMetaTag(HTMLMetaTag::META_TITLE, 'User Registration'),

		    new HTMLHeaderScript(__DIR__ . '\assets\form-register.js'),
		    new HTMLHeaderStyleSheet(__DIR__ . '\assets\form-register.css'),

		    new HTMLElement('h2', 'content-title', 'Registration'),

		    new HTMLElement('fieldset', 'fieldset-generate',
			    new HTMLElement('legend', 'legend-generate toggle', "Generate a new PGP key pair to secure your personal information"),

			    "Choose a new user ID<br/>",
			    new HTMLInputField(self::PARAM_USER, null, null, 'field-user'),

			    "<br/><br/>Optionally provide an email address for others to see<br/>",
			    new HTMLInputField(self::PARAM_EMAIL, $inviteeEmail, 'email', 'field-email',
                    ($inviteeEmail ? new Attributes('disabled', 'disabled') : null)
                ),

			    "<br/><br/>Choose an optional passphrase for your private key<br/>",
			    new HTMLPasswordField(self::PARAM_PASSPHRASE, 'field-passphrase'),

			    "<br/><br/>Generate your user account PGP key pair<br/>",
			    new HTMLButton(self::PARAM_GENERATE, 'Generate', null, null, 'field-generate',
				    new Attributes('disabled', 'disabled')
			    )
		    ),

		    "<br/><br/>",
		    new HTMLElement('fieldset', 'fieldset-public-key',
			    new HTMLElement('legend', 'legend-public-key toggle', "Your PGP Public Key"),

			    "Enter a PGP public key you'll use to identify yourself publicly<br/>",
			    new HTMLTextAreaField(self::PARAM_PUBLIC_KEY, null, 'field-public-key',
				    new Attributes('rows', 14, 'cols', 80),
				    new Attributes('placeholder', self::PLACEHOLDER),
				    new RequiredValidation(),
				    new ValidationCallback(
					    function (IRequest $Request, $publicKeyString) {
						    $PublicKey = new PublicKey($publicKeyString);
						    $userID    = $PublicKey->getUserID();

						    $Validation = new UserNameOrEmailValidation();
						    $Validation->validate($Request, $userID);

						    $shortKey  = $PublicKey->getKeyID();
						    $PGPSearch = new PGPSearchCommand($shortKey, '');
						    $PGPSearch->executeWithCallback($Request, function () use ($shortKey) {
							    throw new \Exception("Short key conflict: " . $shortKey);
						    });

						    $timestamp = $PublicKey->getTimestamp();
						    if (Config::$REGISTRATION_LIMIT !== false
							    && ($timestamp < time() - Config::$REGISTRATION_LIMIT - 24*60*60)	// - 1 day buffer
						    )
							    throw new \Exception("Provided public key was created more than 24 hours ago. Please register with a new key pair");
					    }
				    )
			    ),

			    "<br/>",
			    new HTMLElement('fieldset', 'fieldset-load-file inline',
				    new HTMLElement('legend', 'legend-tools toggle', "Load PGP Public Key File"),
				    new HTMLFileInputField(self::PARAM_LOAD_FILE, '.pub, .asc', 'field-load')
			    ),
			    new HTMLElement('fieldset', 'fieldset-load-storage inline',
				    new HTMLElement('legend', 'legend-storage toggle', "Load From Storage"),
				    new HTMLButton(self::PARAM_LOAD_STORAGE, "Load",
					    new Attributes('disabled', 'disabled')
				    )
			    )
		    ),

		    "<br/><br/>",
		    new HTMLElement('fieldset', 'fieldset-submit',
			    new HTMLElement('legend', 'legend-submit', "Submit Registration"),
			    new HTMLButton(self::PARAM_SUBMIT, 'Register', null, 'submit', 'field-submit'),
			    new HTMLButton(self::PARAM_RESET, 'Reset Form', null, 'reset', 'field-reset')
		    )
	    );

	    $Form->setFormValues($Request);
	    if(!$Request instanceof IFormRequest)
		    return $Form;


	    $publicKeyString = $Form->validateField($Request, self::PARAM_PUBLIC_KEY);

	    try {
            // todo: import before db

		    $Account = AccountEntry::create($Request, $publicKeyString, $inviteeEmail, $inviterFingerprint);
		    $fingerprint = $Account->getFingerprint();
		    $this->mNewAccountFingerprint = $fingerprint;

	    } catch (PGPKeyAlreadyImported $ex) {
		    throw new ValidationException($Form, $ex->getMessage());
	    }

	    $Account = AccountEntry::get($fingerprint);

//	    if($Request instanceof ISessionRequest) {
//		    if($Request->isStarted())
//			    $Request->endSession();
//		    $Request->startSession();
//		    //UserSession::setUserFingerprintFromSession($Request, $User->getFingerprint());
//	    }
        if($Request instanceof ISessionRequest)
            $Request->destroySession();
	    return new RedirectResponse(Login::getRequestURL($Account->getFingerprint()),
		    "User registered successfully - " . $Account->getName(), 5);
    }

	public function getNewAccountFingerprint() {
		if(!$this->mNewAccountFingerprint)
			throw new \InvalidArgumentException("Execution did not complete");
		return $this->mNewAccountFingerprint;
	}

    // Static

    public static function getRequestURL() {
        return self::FORM_ACTION;
    }

    /**
     * Handle this request and render any content
     * @param IBuildRequest $Request the build request inst for this build session
     * @return void
     * @build --disable 0
     * Note: Use doctag 'build' with '--disable 1' to have this IBuildable class skipped during a build
     */
    static function handleBuildStatic(IBuildRequest $Request) {
        $RouteBuilder = new RouteBuilder($Request, new SiteMap());
        $RouteBuilder->writeRoute('ANY ' . self::FORM_ACTION, __CLASS__,
            IRequest::MATCH_NO_SESSION |
            IRequest::NAVIGATION_ROUTE,
            "Register");
    }

	/**
	 * Route the request to this class object and return the object
	 * @param IRequest $Request the IRequest inst for this render
	 * @param Object[]|null $Previous all previous response object that were passed from a handler, if any
	 * @param null|mixed $_arg [varargs] passed by route map
	 * @return void|bool|Object returns a response object
	 * If nothing is returned (or bool[true]), it is assumed that rendering has occurred and the request ends
	 * If false is returned, this static handler will be called again if another handler returns an object
	 * If an object is returned, it is passed along to the next handler
	 */
	static function routeRequestStatic(IRequest $Request, Array &$Previous = array(), $_arg = null) {
		return new ExecutableRenderer(new Register(), true);
	}
//
//	/**
//	 * Perform a unit test
//	 * @param IUnitTestRequest $Test the unit test request inst for this test session
//	 * @return void
//	 * @test --disable 0
//	 * Note: Use doctag 'test' with '--disable 1' to have this ITestable class skipped during a build
//	 */
//	static function handleStaticUnitTest(IUnitTestRequest $Test) {
//		$TestUser = new TestUser($Test, 'register');
////		$TestUser->deleteAccount($Test);
////		$TestUser = new TestUser($Test, 'register');
//
//		$publicKey = $TestUser->exportPublicKey($Test);
//		$privateKey = $TestUser->exportPrivateKey($Test);
//		$TestUser->deleteAccount($Test);
//
//		$TestUser->importPrivateKey($Test, $privateKey);
//		$TestUser->deleteAccount($Test);
//
//		Register::$mTestMode = true;
//		$Register = new Register();
//		Register::$mTestMode = false;
//
//		$Test->setRequestParameter(Register::PARAM_PUBLIC_KEY, $publicKey);
//		$Register->execute($Test);
//		$fp = $Register->getNewAccountFingerprint();
//		$Test->assertEqual($fp, $TestUser->getFingerprint());
//
//		$TestUser->importPrivateKey($Test, $privateKey);
//
//	}
}

