## Description

Demonstrates how to build a smart contract between a Customer and a Worker to
generate a vanity keypair


## Details

A Medium post describing this smart contract is available at: https://medium.com/@zulucrypto_23845/distributed-trustless-workers-with-stellar-e197fd1b77f6

See the "Code Setup" section if you'd like to try out this smart contract. 

## Setup

**Install dependencies with composer**

```bash
$ cd distributed-worker/
$ composer install
```


**Generate Keypairs**

To run this example on the test network, you will need to edit `common.php` and
generate new keypairs for all of the testing accounts.

One easy way to do this is to change the "passphrase" used in the call to `Keypair::newFromMnemonic`:

```php
$workerKeypair = Keypair::newFromMnemonic('...', 'CUSTOM_TEXT_HERE');
```

**Fund accounts**

Run `setup.php` to fund demo accounts:

```bash
$ php setup.php
```

**Start executing the contract**

Start by looking at the code in `10-customer-requests.php` and by running it:

```bash
$ cd distributed-worker/
$ php 10-customer-requests.php
```
