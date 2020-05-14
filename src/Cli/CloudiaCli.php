<?php


namespace Cloudia\Cli;

use Phore\CliTools\Helper\ColorOutput;
use Phore\Core\Exception\NotFoundException;
use Phore\CliTools\Helper\GetOptResult;
use Phore\CliTools\PhoreAbstractCli;
use Phore\Core\Exception\InvalidDataException;
use Phore\FileSystem\PhoreTempFile;
use Phore\FileSystem\PhoreDirectory;
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
            "init" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                //Defaults to current folder
                $cloudiaFile = getcwd() . "/cloudia.yaml";
                $skip_prompt = false;
                $passphrase = "";

                //Evaluate flags
                if(!empty($argv)) {
                    for ($i=0, $len=count($argv); $i<$len; $i++) {
                        if($argv[$i] === "--yes" || $argv[$i] === "-y") {
                            $skip_prompt = true;
                        } else if($argv[$i] === "--passphrase" || $argv[$i] === "-p") {
                            $passphrase = $argv[$i+1];
                            $i++;
                        } else {
                            throw new InvalidDataException("Invalid argument:" . $argv[$i]);
                        }
                    }
                }

                //Skipping std input if flag -y is set
                if(!$skip_prompt) {
                    $isRequired = "Y" . PHP_EOL;
                    if(phore_file($cloudiaFile)->isFile()) {
                        $this->out("Key file(cloudia.yaml) exists, Do you want to create new?(Y/N): ");
                        $handle = fopen ("php://stdin","r");
                        $isRequired = fgets($handle);
                        fclose($handle);
                    }

                    if(strcasecmp("Y" . PHP_EOL, $isRequired) == 0) {
                        //Check if Passphrase is set using argumnets
                        if(empty($passphrase)) {
                            $this->out("Enter passphrase for encrypting the private key(Leave empty to generate):");
                            $handle = fopen ("php://stdin","r");
                            $passphrase = fgets($handle);
                            fclose($handle);
                        }
                    }
                    //End execution if user input is N
                    else if(strcasecmp("N" . PHP_EOL, $isRequired) == 0) {
                        $this->emergency(ColorOutput::Str("New keys are NOT generated" , "red") . PHP_EOL);
                        return;
                    } else {
                        throw new InvalidDataException("Invalid option ". $isRequired . "New keys are NOT generated");
                    }
                }
                //Generate passphrase if no user input
                $passphrase = trim($passphrase);
                if(empty($passphrase)) {
                    $passphrase = phore_random_str(45);
                    $this->out("Random passphrase->" . PHP_EOL);
                    $this->out($passphrase . PHP_EOL);
                }
                $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                $keys = $asyncEncrypter->createKeyPair();
                $keys["private_key"] = $syncEncrypter->encrypt($keys["private_key"]);
                //Write the keys to file
                phore_file($cloudiaFile)->set_yaml(["sec" => $keys]);
                $this->out("Keys saved in-> " . $cloudiaFile . PHP_EOL);
            },

            "encrypt" => function (array $argv) {
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $tmpFile = new PhoreTempFile();
                //Defaults to current folder
                $folder = getcwd();
                //Evaluate flags
                if(!empty($argv)) {
                    for ($i=0, $len=count($argv); $i<$len; $i+=2) {
                        if($argv[$i] === "--directory" || $argv[$i] === "-C") {
                            $folder = $argv[$i+1];
                        }
                        else if($argv[$i] === "--input" || $argv[$i] === "-I") {
                            $secret = $argv[$i+1];
                        } else {
                            throw new InvalidDataException("Invalid argument:" . $argv[$i]);
                        }
                    }
                }
                //Get public key from yaml file
                $cloudiaFile = $folder . "/cloudia.yaml";
                if(phore_file($cloudiaFile)->isFile()) {
                    $keys = phore_file($cloudiaFile)->get_yaml();
                    $publicKey = $keys["sec"]["public_key"];
                } else {
                    throw new NotFoundException("Key file(cloudia.yaml) not found. Run 'cloudia init' to generate or specify the folder with -f flag");
                }
                //Prompt if input which needs to be encrypted is not provided      
                if(empty($secret)) {
                    $this->out("Enter the secret which needs to be encrypted:" . PHP_EOL);
                    passthru("editor $tmpFile");
                    $secret = $tmpFile->get_contents();
                }
                //Write the encrypted input to std out
                $secret=trim($secret);
                if(!empty($secret)) {
                    $this->out("Encrypted Secret->" . PHP_EOL);
                    $this->out("{ENC-" . $asyncEncrypter->encrypt($secret, $publicKey) . "}" . PHP_EOL);    
                } else {
                    throw new InvalidDataException("Invalid secret: secret is blank or only whitespaces");
                }
                             
            },

            "decrypt" => function (array $argv) {
                //Defaults to current folder
                $folder = getcwd();
                $asyncEncrypter = new PhoreSecretBoxAsync();
                $extension = [];
                //get passphrase from environment variable if available
                $passphrase = getenv("CLOUDIA_SECRET");
                //Evaluate flags
                if(!empty($argv)) {
                    for ($i=0, $len=count($argv); $i<$len; $i+=2) {
                        if($argv[$i] === "--directory" || $argv[$i] === "-C") {
                            $folder = $argv[$i+1];
                        }
                        else if($argv[$i] === "--passphrase" || $argv[$i] === "-p") {
                            $passphrase = $argv[$i+1];
                        } else if($argv[$i] === "--type" || $argv[$i] === "-t") {
                           array_push($extension, $argv[$i+1]);
                        } else {
                            throw new InvalidDataException("Invalid argument:" . $argv[$i]);
                        }
                    }
                }
                //Default to yaml and yml files
                if(empty($extension)) {
                    array_push($extension, "yaml", "yml");
                }
                $cloudiaFile = $folder . "/cloudia.yaml";
                if(phore_file($cloudiaFile)->isFile()) {
                    $keys = phore_file($cloudiaFile)->get_yaml();
                    $privateKey = $keys["sec"]["private_key"];
                } else {
                    throw new NotFoundException("Key file(cloudia.yaml) not found in " . $folder);
                }
                // prompt if passphrase to decrypt private key is not provided as arguments  
                if(empty($passphrase)) {
                    $this->out("Enter passphrase for decrypting the private key:");
                    $handle = fopen ("php://stdin","r");
                    $passphrase = fgets($handle);
                    fclose($handle);
                }

                //Symmetric decrypt private key
                $syncEncrypter = new PhoreSecretBoxSync($passphrase);
                $privateKey = $syncEncrypter->decrypt($privateKey);

                //Recursively walking through the folder for matching file types
                $this->out("Recursively looking for ". implode(",",$extension) ." files in -> " . $folder . PHP_EOL);
                $phoreDir = new PhoreDirectory($folder);
                $phoreDir -> walkR(function($file) use ($asyncEncrypter, $privateKey, $extension) {
                    $phoreFile=phore_file($file);
                    if(in_array($phoreFile->getExtension(), $extension)) {
                        $this->out("Processing...." . $phoreFile->getFilename() . PHP_EOL);
                        $contents = $phoreFile->get_contents();
                        //Get the encrypted text, decrypt and replace
                        preg_match_all("/{ENC-(.+?)}/", $contents, $secrets, PREG_SET_ORDER);
                        if(!empty($secrets)) {
                            $this->out("Replacing secrets in file -> " . $phoreFile->getFilename() . PHP_EOL);
                            foreach($secrets as $secret) {
                                $contents = str_replace($secret[0], str_replace("\n", "", 
                                                $asyncEncrypter->decrypt($secret[1], $privateKey)), $contents);
                            }
                        // Write the content back to file
                        $phoreFile->set_contents($contents);
                        }
                    } 
                 });  
            },
        ]);
    }
}