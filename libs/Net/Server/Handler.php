<?php
require_once 'Net/Server/Handler.php';
//require_once 'lib/FastAGI/Command.php';

/**
 *
 * @author Morten Amundsen
 */
final class FastAGI extends Net_Server_Handler
{

    /**
     *
     * @param integer $clientId
     * @param string $data
     */
    public function onReceiveData($clientId = 0, $data = '')
    {
        try {
            $cmd = new FastAGI_Command($data, $this->_server);

            $retval = $cmd->execute();

            $this->convertAndReturn($retval);

            $this->setAsteriskVar('fastagi_status', 'OK');
        } catch (Exception $e) {
            $this->setAsteriskVar('fastagi_error_message', $e->getMessage());
            $this->setAsteriskVar('fastagi_status', 'ERROR');
        }

        $this->_server->closeConnection();
    }

    /**
     *
     * @param mixed $retval
     */
    protected function convertAndReturn($retval)
    {
        if (is_array($retval) or is_object($retval)) {
            foreach ($retval as $key => $value) {
                if (!is_object($value) and ! is_array($value)) {
                    $this->setAsteriskVar($key, $value);
                }
            }
        } else {
            $this->setAsteriskVar('return_value', $retval);
        }
    }

    /**
     *
     * @param string $var Asterisk variable name
     * @param string $value Asterisk variable value
     */
    protected function setAsteriskVar($var, $value)
    {
        $this->_server->sendData("SET VARIABLE \"{$var}\" \"{$value}\"");
    }
}

/**
 * Description of Command
 *
 * @author Morten Amundsen
 */
class FastAGI_Command {

    protected $class;
    protected $method;
    protected $params;
    protected $channel;
    protected $lang;
    protected $type;
    protected $uniqueid;
    protected $callerid;
    protected $calleridname;
    protected $dnid;
    protected $context;
    protected $exten;
    protected $pri;
    protected $connection;

    public function __construct($msg, $connection)
    {
        $lines = explode("\n", $msg);

        $this->connection = $connection;

        foreach ($lines as $line) {
            $parts = explode(':', trim($line));
            switch ($parts[0]) {
                case 'agi_request':
                    unset($parts[0]);
                    $query = implode(':', $parts);

                    if ($data = parse_url(trim($query))) {
                        if (!empty($data['query'])) {
                            parse_str($data['query'], $this->params);
                        } else {
                            $this->params = array();
                        }

                        $pathparts = explode('/', substr($data['path'], 1, strlen($data['path']) - 1));
                        $cmd = $pathparts[count($pathparts) - 1];
                        unset($pathparts[count($pathparts) - 1]);
                        if (!count($pathparts)) {
                            $this->class = $cmd;
                            $this->method = 'default';
                        } else {
                            $this->class = implode('_', $pathparts);
                            $this->method = $cmd;
                        }
                    } else {
                        throw new Exception("Query not understood: {$query}");
                    }
                    break;
                case 'agi_channel':
                    $this->channel = $parts[1];
                    break;
                case 'agi_uniqueid':
                    $this->uniqueid = $parts[1];
                    break;
                case 'agi_callerid':
                    $this->callerid = $parts[1];
                    break;
                case 'agi_context':
                    $this->context = $parts[1];
                    break;
                case 'agi_extension':
                    $this->exten = $parts[1];
                    break;
                case 'agi_priority':
                    $this->pri = $parts[1];
                    break;
            }
        }
    }

    /**
     *
     * @return mixed
     */
    public function execute()
    {
        Zend_Loader::loadClass($this->class);

        if (class_exists($this->class)) {
            $class = $this->class;
            $obj = new $class;

            if (method_exists($obj, $this->method)) {
                return call_user_func_array(array($obj, $this->method), $this->params);
            } else {
                throw new Exception("Method {$this->method} does not exist in class {$this->class}");
            }
        } else {
            throw new Exception("No such class '{$this->class}'");
        }
    }
}
?>