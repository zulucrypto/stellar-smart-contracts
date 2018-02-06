<?php

require 'vendor/autoload.php';

use \ZuluCrypto\StellarSdk\Keypair;
use \ZuluCrypto\StellarSdk\Server;

$stellarNetwork = Server::testNet();

// -- Accounts for actors in the smart contract

$workerKeypair = Keypair::newFromMnemonic('illness spike retreat truth genius clock brain pass fit cave bargain toe', 'worker');
$customerKeypair = Keypair::newFromMnemonic('illness spike retreat truth genius clock brain pass fit cave bargain toe', 'customer');


// -- Accounts that do not exist yet
$vanityKeypair = Keypair::newFromMnemonic('illness spike retreat truth genius clock brain pass fit cave bargain toe', 'vanity');

$escrowKeypair = Keypair::newFromMnemonic('illness spike retreat truth genius clock brain pass fit cave bargain toe', 'escrow');