<?php
/**
 * Created by PhpStorm.
 * User: ari
 * Date: 12/18/2014
 * Time: 5:40 PM
 */
namespace Site\PGP\Commands;

use Site\PGP\Exceptions\PGPPrivateKeyNotFound;
use Site\PGP\PGPCommand;
use CPath\Request\IRequest;

class PGPDeleteSecretAndPublicKeyCommand extends PGPCommand
{
	const ALLOW_STD_ERROR = true;
	const CMD = "--batch --delete-secret-and-public-key %s";

	public function __construct($fingerprint) {
		$command = sprintf(static::CMD, preg_replace('/[^\w\d]*/', '', $fingerprint));
		parent::__construct($command);
	}

	/**
	 * Execute a command and return a response. Does not render
	 * @param IRequest $Request
	 * @throws Exceptions\PGPCommandException
	 * @throws PGPPrivateKeyNotFound
	 * @throws \Exception
	 * @return PGPImportPublicKeyCommand the execution response
	 */
	function execute(IRequest $Request = null) {
		$Response = parent::execute($Request);

		if ($Exs = $Response->getExceptions())
			throw $Exs[0];

		return $this->update("Public and Secret key deleted");
	}
}