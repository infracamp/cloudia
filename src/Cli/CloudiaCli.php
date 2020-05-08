<?php


namespace Cloudia\Cli;


use Phore\CliTools\Helper\GetOptResult;
use Phore\CliTools\PhoreAbstractCli;
use Phore\FileSystem\PhoreTempFile;
use Phore\Core\Helper\PhoreSecretBoxAsync;
use Phore\Core\Helper\PhoreSecretBoxSync;

class CloudiaCli extends PhoreAbstractCli
{

    public function __construct()
    {
        parent::__construct(
            "IaC service tool",
            __DIR__ . "/../cli_help.txt",
            "p:m",
            ["file"]

        );
    }


    /**
     * @param array $argv
     * @param int $argc
     * @param GetOptResult $opts
     * @throws \Phore\CliTools\Ex\UserInputException
     */
    protected function main(array $argv, int $argc, GetOptResult $opts)
    {
        $this->execMap([
            "create_keypair" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $secFile = getcwd() . "/sec.json";
                $this->out("Enter passphrase for encrypting the private key(Leave empty to generate):");
                $handle = fopen ("php://stdin","r");
                $passphrase = fgets($handle);
                fclose($handle);

                if(ctype_space($passphrase)) {
                    $passphrase = phore_random_str(45);
                    $this->out("Random passphrase->" . PHP_EOL);
                    $this->out($passphrase . PHP_EOL);
                }
                $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                $keys = $asyncEncrypter->createKeyPair();
                $keys["private_key"] = $syncEncrypter->encrypt($keys["private_key"]);

                phore_file($secFile)->set_json(["sec" => $keys]);
                $this->out("Keys saved in-> " . $secFile . PHP_EOL);
            },

            "encrypt_async" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $this->out("Enter public key for encrypting secret:");
                $handle = fopen ("php://stdin","r");
                $publicKey = fgets($handle);
                fclose($handle);
                $this->out("Enter the secret which needs to be encrypted:");
                $handle = fopen ("php://stdin","r");
                $secret = fgets($handle);
                fclose($handle);
                $this->out("Encrypted Secret->" . PHP_EOL);
                $this->out($asyncEncrypter->encrypt($secret, $publicKey) . PHP_EOL);
            },

            "decrypt_async" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $this->out("Enter passphrase for decrypting the private key:");
                $handle = fopen ("php://stdin","r");
                $passphrase = fgets($handle);
                fclose($handle);
                $this->out("Enter the encrypted private key:");
                $handle = fopen ("php://stdin","r");
                $privateKey = fgets($handle);
                fclose($handle);
                $this->out("Enter the secret which needs to be decrypted:");
                $handle = fopen ("php://stdin","r");
                $secret = fgets($handle);
                fclose($handle);
                $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                $privateKey=$syncEncrypter->decrypt($privateKey);
                $this->out("Decrypted Secret->" . PHP_EOL);
                $this->out($asyncEncrypter->decrypt($secret, $privateKey) . PHP_EOL);
            },

            "say_hello" => function (array $argv) {
                $this->out("Hello!" . PHP_EOL);
            },

            // open the default editor to enter some secret
            "open_editor" => function(array $argv) {

                $tmpFile = new PhoreTempFile();

                // Open the editor
                passthru("editor $tmpFile");

                $this->out("You wrote:" . $tmpFile->get_contents());

                // Temp file should be deleted on __destuct()
            }
        ]);
    }
}