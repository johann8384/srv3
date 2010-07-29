<?php
/*	File		:	FastAGI.php
**	Version		:	0.0
**	Author		:	Dr. Clue	(A.K.A. Ian A. Storms)
**	Description	:	This PHP file supports the implementation of a FastAGI daemon for Asterisk.
**	The presence of the (#!/usr/bin/php -q) on the first line is because it is run like a shell script.
**	This file must be marked with the executable attribute (chmod a+x FastAGI.php)
**	This Fast AGI damon is also designed to work with init.d if desired so that it will boot with your server.
**
**	FastAGI.php	is passed the name of your custom PHP script from the dialplan and loads it at call time
**	so the development cycle is extremely easy (edit / save / dial), which is a vast improvement over
**	having to reload the asterisk, and.or restart a FastAGI daemon for each little edit.
**
**	If you received this file as part of a .zip , there should also be a sample init.d script
**	"FastAGId" that you can use to get this daemon to run at boot.
**
**	Dependancies	:	class.daemon.inc	-This provides the actual daemon functionality and
**							can be used to create other custom daemons.
**	CONSTRUCTOR	:	$oDaemon = new CLASSdaemonAGI(Array(	"port"		=>4544	,
**									"iVerbosity"	=>0	));
**						port		- Obviously the port one wants the daemon to run on.
**						iVerbosity	- At 0 no diagnostic output occurs , but as the number
**								is increased FastAGI.php gets more chatty
**						szAMIusername	- asterisk etc/asterisk/manager.conf [username].
**						szAMIsecret	- asterisk etc/asterisk/manager.conf secret.
**	DIALPLAN	:	exten => 5454,1,AGI(agi://127.0.0.1:4544/yourphpscript.inc)
**				exten => h,n,Hangup()
**
**				The "yourphpscript.inc" can be named anything you want as long as it ends in ".inc"
**				The script will be loaded and your custom function named "FastAGI_main()" will
**				be called and passed an instance of the CLASSdaemonAGI class.
**
**				The CLASSdaemonAGI->aAGIvariables will contain a name/value array
**				of the variables passed in by asterisk.
**
**	METHODS		:	Some of the more important methods are mentioned here.
**
**					$CLASSdaemonAGI->AGIcommand("ANSWER");
**						Executes an AGI command and returns the result as a string.
**						See	:http://www.voip-info.org/wiki/view/Asterisk+AGI
**					$CLASSdaemonAGI->EXEcommand("SET CALLERID(name)=SomeName"			);
**						Executes an asterisk Application (dial plan function)
**						See	:http://www.voip-info.org/wiki/view/Asterisk+-+documentation+of+application+commands
**					$CLASSdaemonAGI->AMIcommand($szCommand,&$aResultOut=Array(),$bFlush=TRUE)
**						Executes an AMI command and returns the result as a name/value array.
**						If you pass in an array it will be used.
**						The $bFlush indicates if the passed in array should be cleared.
**						See	:http://www.voip-info.org/wiki/view/Asterisk+manager+API
**					$CLASSdaemonAGI->AMISetVar($szVar,$szValue,$szChannel=FALSE)
**						Sets a variable via AMI and defaults to the current channel
**						if same is not set.
**					$CLASSdaemonAGI->AMIGetVar($szVar,$szChannel=FALSE)
**						Sets a variable via AMI and defaults to the current channel
**						if same is not set.
**	NOTES		:
**	URLS		:
*/

require_once("class.daemon.inc");
if(file_exists("configure.php"))
	{
	require_once("configure.php");
	if(isset($MySql_szUser)){require_once("class.mysql.inc");}
	}

class CLASSdaemonAGI extends CLASSdaemon
{
	var $aAGIvariables		=Array()		;	// Array of AGI variables passed in.
	var $szAMIusername		=""			;	// asterisk etc/asterisk/manager.conf [username].
	var $szAMIsecret		=""			;	// asterisk etc/asterisk/manager.conf secret.
	var $iAMIserverPort		="5038"			;	// Port that the asterisk AMI server runs on.
	var $iAMIserverHost		="127.0.0.1"		;	// Host that the asterisk AMI server runs on.
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
/*	Function	:	loadAGIvariables()
**	Parameters	:	None
**	Returns		:	None
**	Description	:	
*/
function loadAGIvariables()
	{
	$szSocketRead=$this->socket_read_response($this->connection);
	$this->result2array($szSocketRead,$this->aAGIvariables);
	$this->console_message("\n AGI variables=".print_r($this->aAGIvariables	,TRUE),2);
	}
/*	Function	:	Speak()
**	Parameters	:	$szString - Text to be spoken
**	Returns		:	AGI result string.
**	Description	:	Uses he flite text 2 speech application to say the text to the caller.
**			This is mostly handy for debugging messages or other ad hoc speech.
*/
function Speak($szString)
	{
	return $this->EXEcommand("Flite \"$szString\"");
	}
/*	Function	:	playAudio()
**	Parameters	:	$szCustomAudioFile
**	Returns		:	AGI result string
**	Description	:	Plays a custom wav file form the asterisk custom recordings directory.
**			often this directory is located at /var/lib/asterisk/sounds/custom/
*/
function playAudio($szCustomAudioFile)
	{
	return $this->EXEcommand("PlayBack custom/$szCustomAudioFile");
	}
/*	Function	:	Answer()
**	Parameters	:	None
**	Returns		:	AGI response string
**	Description	:	Simply answers the phone.
*/
function Answer()
	{
	return $this->AGIcommand("Answer");
	}
/*	Function	:	Busy()
**	Parameters	:	None
**	Returns		:	AGI response string
**	Description	:	Simply indicates a busy signal and hangs up.
*/
function Busy()
	{
	return $this->AGIcommand("Busy");
	}
/*	Function	:	
**	Parameters	:	
**	Returns		:	
**	Description	:	
*/
function Verbose($iLevel,$szMessage)
	{
	return $this->AGIcommand("Verbose $iLevel <FastAGI.php $szMessage>");
	}

/*	Function	:	AGIcommand()
**	Parameters	:	$szCommand
**	Returns		:	String
**	Description	:	
*/
function AGIcommand($szCommand)
	{
	return $this->socket_send_command($szCommand,1);
	}
/*	Function	:	AGIGetVar()
**	Parameters	:	$szVarname
**	Returns		:	String - returns the variable value or FALSE if not found.
**	Description	:	When a FastAGI is called , asterisk passes an array of call related variables.
**			The CLASSdeamonAGI parses these into an Array and makes them available,
*/
function AGIGetVar($szVarname)
	{
	if(isset($this->aAGIvariables[$szVarname]))return $this->aAGIvariables[$szVarname];
	return FALSE;
	}
/*	Function	:	AGISetVar()
**	Parameters	:	$szVarname,$szValue
**	Returns		:	Array	
**	Description	:	Sets a variable.
**			
*/
function AGISetVar($szVarname,$szValue)
	{
	$szCommand="SET $szVarname=$szValue"	;
	$aResult=$this->EXEcommand($szCommand);
	$this->console_message("AGISetVar result =".print_r($aResult,TRUE),-1);
	return $aResult;
//
//	if(isset($this->aAGIvariables[$szVarname]))return $this->aAGIvariables[$szVarname];
//	return FALSE;
	}
/*	Function	:	EXEcommand()
**	Parameters	:	$szCommand
**	Returns		:	
**	Description	:	
*/
function EXEcommand($szCommand)
	{
	return $this->socket_send_command("EXEC ".$szCommand);
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
		$aResultOut[substr($value, 0, strpos($value, ':'))] = trim(substr($value, strpos($value, ':') + 1));
		}
	return $aResultOut;
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
/*	Function	:	AMIGetVar()
**	Parameters	:	$szVar,$szChannel=FALSE
**	Returns		:	Array	- Namevalue pairs
**	Description	:	
*/
function AMIGetVar($szVar,$szChannel=FALSE)
	{
	if($szChannel===FALSE)$szChannel=$this->aAGIvariables['agi_channel'];
	$aResult=$this->AMIcommand(<<<EOT
Action: Getvar
Channel: $szChannel
Variable: $szVar

EOT
);
	return (isset($aResult)?$aResult["Value"]:FALSE);
	}
/*	Function	:	AMISetVar()
**	Parameters	:	$szVar,$szValue,$szChannel=FALSE
**	Returns		:	None
**	Description	:	
*/
function AMISetVar($szVar,$szValue,$szChannel=FALSE)
	{
	if($szChannel===FALSE)$szChannel=$this->aAGIvariables['agi_channel'];
return $this->AMIcommand(<<<EOT
Action: Setvar
Channel: $szChannel
Variable: $szVar
Value:$szValue

EOT
);
	}
/*	Function	:	HIT($socket)
**	Parameters	:	$socket
**	Returns		:	None
**	Description	:	This function is called when a socket request (HIT) is received.
**				as a result of a call from the Dial plan
*/
function HIT($socket)
	{
	$this->stdError		=fopen("php://stderr","w");
	$this->loadAGIvariables();
	if(isset($this->aAGIvariables['agi_network_script']))
	if(!empty($this->aAGIvariables['agi_network_script']))
		{
		require_once("FastAGI_".$this->aAGIvariables['agi_network_script'].".inc");
		FastAGI_main($this);
		return;
		}
	$this->socket_send_command("ANSWER");
	$this->socket_send_command("EXEC Flite \" Fast A G I requires a method be passed from the dial plan , thank you\"");
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
Events: off

EOT;
	$this->AMIcommand($szCommand);
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

	if(empty($MySql_szUser		))return;
	if(empty($MySql_szPassword	))return;
	$this->	$oMYSQLclass=new MYSQLclass();

	}

}// end class daemon

// Values for startup are expected to be in an external file called configure.php
$oDaemon = new CLASSdaemonAGI(Array("port"=>$iAGIport,"iVerbosity"=>$iAGIverbosity,"szAMIusername"=>$szAMIusername,"szAMIsecret"=>$szAMIsecret));
$oDaemon->server_main($argc,$argv);

?>
