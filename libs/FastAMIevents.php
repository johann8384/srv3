#!/usr/bin/php -q
<?php
/*	File		:	FastAMIevents.php
**	Author		:	Dr. Clue	(A.K.A. Ian A. Storms)
**	Description	:	This PHP file supports the implementation of
**				and Asterisk AMI event listener.
**	NOTES		:	nv_fax_detect
**	URLS		:	http://www.voip-info.org/wiki/view/asterisk+manager+events
*/

require_once("class.daemon.inc");
if(file_exists("configure.php"))
	{
	require_once("configure.php");
	if(isset($MySql_szUser)){require_once("class.mysql.inc");}
	}

class CLASSdaemonAAMIevents extends CLASSdaemon
{
	var $szAMIusername		=""			;	// asterisk etc/asterisk/manager.conf [username].
	var $szAMIsecret		=""			;	// asterisk etc/asterisk/manager.conf secret.
	var $iAMIserverPort		="5038"			;	// Port that the asterisk AMI server runs on.
	var $iAMIserverHost		="127.0.0.1"		;	// Host that the asterisk AMI server runs on.
	var $AMIsock			=NULL			;	// Socket handle for AMI connection.
/*	CONSTRUCTOR	:	CLASSdaemonAGI()
**	Parameters	:	$szCommand
**	Returns		:	String - The output of the executed command.
**	Description	:
*/
function CLASSdaemonAGI($params=Array())
	{
	$this->szPIDpath	=getcwd();
	$this->szPIDfilepath	=$this->szPIDpath."/".$this->getPIDfilename();
	foreach($params as $key=>$value)$this->$key=$value;
	CLASSdaemon::CLASSdaemon($params);
	}
/*	Function	:	AMIcommand()
**	Parameters	:	$szCommand,&$aResultOut=Array(),$bFlush=TRUE
**	Returns		:	
**	Description	:	
*/
function AMIcommand($szCommand,&$aResultOut=Array(),$bFlush=TRUE)
	{
	if(!is_resource($this->AMIsock))return Array("result"=>"403","data"=>"AMI Not connected");
	$szCommand=implode("\r\n",explode("\n",$szCommand))."\r\n";
	$szResult=$this->socket_send_command($szCommand,$this->AMIsock);
	return $this->result2array($szResult,$aResultOut,$bFlush);
	}
/*	Function	:	loadAGIvariables()
**	Parameters	:	None
**	Returns		:	None
**	Description	:	
*/
function result2array($szResultIn,&$aResultOut=Array(),$bFlush=TRUE)
	{
	if($bFlush==TRUE)$aResultOut=Array();
	$szResultIn	=trim($szResultIn,"\r\n");
	$aResultIn	= explode("\n",$szResultIn);
	foreach($aResultIn as $key => $value)
		{
		$szName=substr($value, 0, strpos($value, ':'));
		$szValue=trim(substr($value, strpos($value, ':') + 1));
		if(empty($szName))continue;
		$aResultOut[$szName] = $szValue;
		}
	return $aResultOut;
	}
/*	Function	:	AMIlogin()
**	Parameters	:	None
**	Returns		:	None
**	Description	:	Called by server_setup() just prior to any forking
**		to allow global access to the AMI manager.
*/
function	AMIlogin()
	{
	if(empty($this->szAMIusername	))return;
	if(empty($this->szAMIsecret	))return;

	if(($this->AMIsock	= @socket_create(AF_INET, SOCK_STREAM, SOL_TCP			))	===FALSE)
		{
		$this->console_message("Call to socket_create failed to create socket: "		.socket_strerror($this->AMIsock)."\n");
		$this->server_stop();exit();
		}
$iRetries =30;
for(;$iRetries>0;$iRetries--)
	{
	if(@socket_connect($this->AMIsock,$this->iAMIserverHost,$this->iAMIserverPort)===FALSE)
		{
		$this->console_message("::server_start Failed to connnect to AMI " .socket_strerror($this->AMIsock)." Attempts remaining ($iRetries)\n");
	sleep(30);
	//	$this->server_stop();exit();
		}else {break;}
	}

	$this->console_message(trim($this->socket_read_response($this->AMIsock)));
	$szCommand=<<<EOT
Action: login
Username: {$this->szAMIusername}
Secret: {$this->szAMIsecret}
Events: on

EOT;
	$this->AMIcommand($szCommand);
	}
/*	Function	:	keeprunning()
**	Parameters	:	None
**	Returns		:	Bool	- Overrides CLASSdaemon::keeprunning to test for the 
**			existence of the program's PID file. This aids the forked child processes
**			in knowing when to die.
**	Description	:	
**		
*/
function keeprunning()
	{
	return file_exists($this->szPIDfilepath);
	}
/*	Function	:	event_monitor_registry()
**	Parameters	:	$aEvent=Array()
**	Returns		:	None
**	Description	:	
**		
*/
function event_monitor_registry($aEvent=Array())
	{
	return;
	}

/*	Function	:	event_monitor()
**	Parameters	:	None
**	Returns		:	None
**	Description	:	A forked child process that continuously reads AMI events.
**		
*/
function event_monitor()
	{
	$pid = pcntl_fork();
	if ($pid == -1)
		{			
		$this->console_message("\n::server_setup read loop failure!\n");		exit();	//fork failure
		}elseif ($pid)	{		return ;//exit();	//close the parent
		}else		{			//child becomes our daemon
		$this->console_message("\n::server_setup read loop starts...");
		sleep(5);
		$this->console_message("\n::server_setup Event monitor child starts\n",-1);
		while(file_exists($this->szPIDfilepath))
			{
			$szEvent=$this->socket_read_response($this->AMIsock,$iTries=3);
			$aEvent	=$this->result2array($szEvent);
			while(sizeof($aEvent)>0)
				{
				if(!isset($aEvent["Event"]))break;
				if($aEvent['Event']=="Registry"){$this->event_monitor_registry($aEvent);break;}
				$aEvent["FAMItimestamp"]=time();
				$this->console_message(print_r($aEvent,TRUE),-1);
				break;
				}
			sleep(1);
			}
		$this->console_message("\n::server_setup Event monitor child ends\n",-1);
		exit();
		}
	}
/*	Function	:	server_setup()
**	Parameters	:	None
**	Returns		:	None
**	Description	:	Called by server_start() just prior to any forking
**		to allow any global resources to be configured.
*/
function server_setup()
	{
	$this->console_message("\n::server_Setup Activating AMI connection :");
	$this->AMIlogin();
//	if(empty($MySql_szUser		))return;
//	if(empty($MySql_szPassword	))return;
//	$this->	$oMYSQLclass=new MYSQLclass();
	$this->event_monitor();
	}

/*	Function	:	server_main()
**	Parameters	:	$argc,$argv
**	Returns		:	None
**	Description	:	Processes command-line or pseudo command line arguments
**			and starts,stops,restarts the daemon or provides help text.
*/
/*function server_main($argc,$argv)
	{
	$this->szPIDstart	=$this->isrunning();
	$bGiveHelp	=FALSE	;
	$bStart		=FALSE	;
	$bStop		=FALSE	;
	$szCommand	="help"	;//Default command
	if($argc<2||$argv[1]=="-help"||$argv[1]=="-h"||$argv[1]=="---help")
		{
		$bGiveHelp=TRUE;$bStart=FALSE;$bStop=FALSE;
		}else{
		if($argc>1&&in_array($argv[1],Array("status"))===TRUE)
			{
			exit(0);
			}
		if($argc>1&&in_array($argv[1],Array("start","stop","restart"))===TRUE)
			{
			$szCommand=$argv[1];
			if($szCommand!="start"	)$bStop		=TRUE;
			if($szCommand!="stop"	)$bStart	=TRUE;
			}
		if($this->szPIDstart!==FALSE&&($bStart===TRUE&&$bStop===FALSE))
			{
			print "\n${argv[0]}  already running $this->szPIDstart \n";
			$bGiveHelp	=TRUE;
			$bStart		=FALSE;
			}
		}
	$this->console_message($this->server_banner());
	if($bGiveHelp	){$this->server_help()	;exit();}
	if($bStop	) $this->server_stop()	;
	if($bStart	) $this->server_start()	;
	}*/
}// end class daemon

// Values for startup are expected to be in an external file called configure.php
//$oDaemon = new CLASSdaemonAAMIevents(Array("port"=>$iAMIport,"iVerbosity"=>$iAGIverbosity,"szAMIusername"=>$szAMIusername,"szAMIsecret"=>$szAMIsecret));
$oDaemon = new CLASSdaemonAAMIevents(Array("port"=>$iAMIport,"iVerbosity"=>0,"szAMIusername"=>$szAMIusername,"szAMIsecret"=>$szAMIsecret));
$oDaemon->server_main($argc,$argv);

/*
if ( sizeof($argv)<2 ) {
        echo "Usage: $argv[0] send|recv|rem|dele ID [msg] \n\n" ;
        echo "   EX: $argv[0] send 1 \"This is no 1\" \n" 	;
        echo "       $argv[0] recv 1 \n" 			;
        echo "       $argv[0] rem 1 \n" 			;
        echo "       $argv[0] dele \n" 				;
        exit;
}

// $SHMKey = ftok(__FILE__, "Z");
$SHMKey = "123456" ;

## Create/Open a shm
$seg = shm_attach( $SHMKey, 1024, 0666 ) ;

switch ( $argv[1] ) {
    case "send":
        shm_put_var($seg, $argv[2], $argv[3]);
        echo "send msg done...\n" ;
        break;
       
    case "recv":
        $data = shm_get_var($seg, $argv[2]);
        echo $data . "\n" ;
        break;
   
    case "rem":
        shm_remove_var($seg, $argv[2]);
        break;

    case "dele":
        shm_remove($seg);
        break;
   
    case "dele2":
        `/usr/bin/ipcrm -M 123456`;
        break;
}*/

?>

