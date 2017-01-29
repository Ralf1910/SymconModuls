<?
// Klassendefinition
class KoboldVR200 extends IPSModule {


	public function Create() {
		// Diese Zeile nicht l�schen.
		parent::Create();
		$this->RegisterPropertyString("BaseURL", "https://nucleo.ksecosys.com/vendors/vorwerk/robots/");
		$this->RegisterPropertyString("SerialNumber", "");
		$this->RegisterPropertyString("SecretKey", "");
		$this->RegisterPropertyInteger("UpdateKoboldWorking", 3);
		$this->RegisterPropertyInteger("UpdateKoboldCharging", 4);
		//Variablenprofil anlegen ($name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon)
		$this->CreateVarProfileVR200IsCharging();
		$this->CreateVarProfileVR200Charge();
		$this->CreateVarProfileVR200Status();

//		$this->CreateVarProfile("WGW.Rainfall", 2, " Liter/m�" ,0 , 10, 0 , 2, "Rainfall");
//		$this->CreateVarProfile("WGW.Sunray", 2, " W/m�", 0, 2000, 0, 2, "Sun");
//		$this->CreateVarProfile("WGW.Visibility", 2, " km", 0, 0, 0, 2, "");
//		$this->CreateVarProfileWGWWindSpeedkmh();
//		$this->CreateVarProfileWGWUVIndex();
		//Timer erstellen
		$this->RegisterTimer("UpdateKoboldData", $this->ReadPropertyInteger("UpdateKoboldCharging")*60*1000, 'VR200_UpdateKoboldData($_IPS[\'TARGET\']);');
	}
	// �berschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {
		// Diese Zeile nicht l�schen
		parent::ApplyChanges();
		if (($this->ReadPropertyString("SerialNumber") != "") && ($this->ReadPropertyString("SecretKey") != "")){
			//Timerzeit setzen in Minuten
			$this->SetTimerInterval("UpdateKoboldData", $this->ReadPropertyInteger("UpdateKoboldCharging")*60*1000);
			// Variablenprofile anlegen
			$this->CreateVarProfileVR200IsCharging();
			$this->CreateVarProfileVR200Charge();
			$this->CreateVarProfileVR200Status();

			$keep = true; // $this->ReadPropertyBoolean("FetchNow");
			$this->MaintainVariable("lastCleaning", "letzte Reinigung", 1, "~UnixTimestampDate", 10, $keep);
			$this->MaintainVariable("version", "Version", 1, "", 10, $keep);
			$this->MaintainVariable("reqId", "Requested ID", 1, "", 20, $keep);
			$this->MaintainVariable("error", "Fehlermeldung", 3, "", 30, $keep);
			$this->MaintainVariable("state", "Status", 1, "", 40, $keep);
			$this->MaintainVariable("action", "Action", 1, "", 50, $keep);
			$this->MaintainVariable("cleaningCategory", "Reinigungskategory", 1, "", 60, $keep);
			$this->MaintainVariable("cleaningMode", "Reinigungsmodus", 1, "", 70, $keep);
			$this->MaintainVariable("cleaningModifier", "Reinigungsmodifier", 1, "", 80, $keep);
			$this->MaintainVariable("cleaningSpotWidth", "Spotbreite", 1, "", 90, $keep);
			$this->MaintainVariable("cleaningSpotHeight", "Spoth�he", 1, "", 100, $keep);
			$this->MaintainVariable("detailsIsCharging", "L�dt", 0, "VR200.isCharging", 110, $keep);
			$this->MaintainVariable("detailsIsDocked", "In der Ladestation", 0, "", 120, $keep);
			$this->MaintainVariable("detailsIsScheduleEnabled", "Zeitplan aktiviert", 0, "", 130, $keep);
			$this->MaintainVariable("detailsDockHasBeenSeen", "Dockingstation gesichtet", 0, "", 140, $keep);
			$this->MaintainVariable("detailsCharge", "Ladezustand", 1, "VR200.Charge", 150, $keep);
			$this->MaintainVariable("metaModelName", "Modelname", 3, "", 160, $keep);
			$this->MaintainVariable("metaFirmware", "Firmware", 3, "", 170, $keep);

			//Instanz ist aktiv
			$this->SetStatus(102);
		} else {
			//Instanz ist inaktiv
			$this->SetStatus(104);
		}
	}



	public function UpdateSerialAndKey() {

	}

	public function UpdateKoboldData() {
		$robotState = $this->doAction("getRobotState");

		SetValue($this->GetIDForIdent("version"), $robotState['version']);
		SetValue($this->GetIDForIdent("reqId"), $robotState['reqId']);
		SetValue($this->GetIDForIdent("error"), $robotState['error']);
		SetValue($this->GetIDForIdent("state"), $robotState['state']);
		SetValue($this->GetIDForIdent("action"), $robotState['action']);
		SetValue($this->GetIDForIdent("cleaningCategory"), $robotState['cleaning']['category']);
		SetValue($this->GetIDForIdent("cleaningMode"), $robotState['cleaning']['mode']);
		SetValue($this->GetIDForIdent("cleaningModifier"), $robotState['cleaning']['modifier']);
		SetValue($this->GetIDForIdent("cleaningSpotWidth"), $robotState['cleaning']['spotWidth']);
		SetValue($this->GetIDForIdent("cleaningSpotHeight"), $robotState['cleaning']['spotHeight']);
		SetValue($this->GetIDForIdent("detailsIsCharging"), $robotState['details']['isCharging']);
		SetValue($this->GetIDForIdent("detailsIsDocked"), $robotState['details']['isDocked']);
		SetValue($this->GetIDForIdent("detailsIsScheduleEnabled"), $robotState['details']['isScheduleEnabled']);
		SetValue($this->GetIDForIdent("detailsDockHasBeenSeen"), $robotState['details']['dockHasBeenSeen']);
		SetValue($this->GetIDForIdent("detailsCharge"), $robotState['details']['charge']);
		SetValue($this->GetIDForIdent("metaModelName"), $robotState['meta']['modelName']);
		SetValue($this->GetIDForIdent("metaFirmware"), $robotState['meta']['firmware']);

	}

	public function UpdateStormWarningData() {
		//Abfrage von Unwetterwarnungen
		if ($this->ReadPropertyBoolean("FetchStormWarning")) {
			$warnings = $this->RequestAPI("/alerts/lang:DL/q/");
			$alerts = array_slice($warnings->alerts, 0, 3);
			$this->SendDebug("WGW Alerts", print_r($alerts, true), 0);
			//Unwetterdaten setzen
			for ($i = 1; $i <= $this->ReadPropertyInteger("FetchStormWarningStormWarningCount"); $i++) {
				if(isset($alerts[$i-1]) && ($alerts[$i-1]->date !== "")) {
					SetValue($this->GetIDForIdent("StormWarning".$i."Date"), strtotime($alerts[$i-1]->date));
				} else {
					SetValue($this->GetIDForIdent("StormWarning".$i."Date"), 0);
				}
				if(isset($alerts[$i-1]) && ($alerts[$i-1]->type !== "")) {
					SetValue($this->GetIDForIdent("StormWarning".$i."Type"), $alerts[$i-1]->type);
				} else {
					SetValue($this->GetIDForIdent("StormWarning".$i."Type"), "");
				}
				if(isset($alerts[$i-1]) && ($alerts[$i-1]->wtype_meteoalarm_name !== "")) {
					SetValue($this->GetIDForIdent("StormWarning".$i."Name"), $alerts[$i-1]->wtype_meteoalarm_name);
				} else {
					SetValue($this->GetIDForIdent("StormWarning".$i."Name"), "");
				}
				if(isset($alerts[$i-1]) && ($alerts[$i-1]->description !== "")) {
					SetValue($this->GetIDForIdent("StormWarning".$i."Text"), str_replace("deutsch:", "", $alerts[$i-1]->description));
				} else {
					SetValue($this->GetIDForIdent("StormWarning".$i."Text"), "");
				}
			}
		}
	}
	private function WithoutSpecialChars($String){
		return str_replace(array("�", "�", "�", "�", "�", "�", "�"), array("a", "o", "u", "A", "O", "U", "ss"), $String);
	}
	//JSON String abfragen und als decodiertes Array zur�ckgeben
	private function RequestAPI($URLString) {
		$location = $this->WithoutSpecialChars($this->ReadPropertyString("Location"));  // Location
		$country = $this->WithoutSpecialChars($this->ReadPropertyString("Country"));  // Country
		$APIkey = $this->ReadPropertyString("APIKey");  // API Key Wunderground
		$this->SendDebug("WGW Requested URL", "http://api.wunderground.com/api/".$APIkey.$URLString.$country."/".$location.".json", 0);
		$content = file_get_contents("http://api.wunderground.com/api/".$APIkey.$URLString.$country."/".$location.".json");  //Json Daten �ffnen
		if ($content === false) {
			throw new Exception("Die Wunderground-API konnte nicht abgefragt werden!");
		}

		$content = json_decode($content);

		if (isset($content->response->error)) {
			throw new Exception("Die Anfrage bei Wunderground beinhaltet Fehler: ".$content->response->error->description);
		}
		return $content;
	}
	// Variablenprofile erstellen
	private function CreateVarProfile($name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon) {
		if (!IPS_VariableProfileExists($name)) {
			IPS_CreateVariableProfile($name, $ProfileType);
			IPS_SetVariableProfileText($name, "", $Suffix);
			IPS_SetVariableProfileValues($name, $MinValue, $MaxValue, $StepSize);
			IPS_SetVariableProfileDigits($name, $Digits);
			IPS_SetVariableProfileIcon($name, $Icon);
		 }
	}

	//Variablenprofil f�r die Battery erstellen
	private function CreateVarProfileVR200IsCharging() {
			if (!IPS_VariableProfileExists("VR200.isCharging")) {
				IPS_CreateVariableProfile("VR200.isCharging", 0);
				IPS_SetVariableProfileText("VR200.isCharging", "", "");
				IPS_SetVariableProfileAssociation("VR200.isCharging", 0, "l�dt", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation("VR200.isCharging", 1, "entl�dt", "", 0x66CC33);
			 }
	}

	//Variablenprofil f�r die Battery erstellen
	private function CreateVarProfileVR200Charge() {
			if (!IPS_VariableProfileExists("VR200.Charge")) {
				IPS_CreateVariableProfile("VR200.Charge", 1);
				IPS_SetVariableProfileValues("VR200.Charge", 0, 100, 1);
				IPS_SetVariableProfileText("VR200.Charge", "", " %");
			 }
	}

	//Variablenprofil f�r den Status erstellen
	private function CreateVarProfileVR200Status() {
			if (!IPS_VariableProfileExists("VR200.Status")) {
				IPS_CreateVariableProfile("VR200.Status", 1);
				IPS_SetVariableProfileText("VR200.Status", "", "");
				IPS_SetVariableProfileAssociation("VR200.Status", 1, "angehalten", "", 0xFFFF00);
				IPS_SetVariableProfileAssociation("VR200.Status", 2, "reinigt", "", 0xFFFF00);
			 }
	}




	// protected $serial;
	// protected $secret;
	// public function __construct($serial, $secret) {
	//	$this->serial = $serial;
	//	$this->secret = $secret;
	// }

	public function getState() {
		return $this->doAction("getRobotState");
	}
	public function startCleaning($eco = false) {
		$params = array("category" => 2, "mode" => ($eco ? 1 : 2), "modifier" => 2);
		return $this->doAction("startCleaning", $params);
	}

	public function startEcoCleaning() {
		$params = array("category" => 2, "mode" => 1, "modifier" => 2);
		return $this->doAction("startCleaning", $params);
	}
	public function pauseCleaning() {
		return $this->doAction("pauseCleaning");
	}

		public function resumeCleaning() {
			return $this->doAction("resumeCleaning");
		}
		public function stopCleaning() {
			return $this->doAction("stopCleaning");
		}
		public function sendToBase() {
			return $this->doAction("sendToBase");
		}
		public function enableSchedule() {
			return $this->doAction("enableSchedule");
		}
		public function disableSchedule() {
			return $this->doAction("disableSchedule");
		}
		public function getSchedule() {
			return $this->doAction("getSchedule");
		}
		protected function doAction($command, $params = false) {
			$result = array("message" => "no serial or secret");
			if($this->ReadPropertyString("SerialNumber") !== false && $this->ReadPropertyString("SecretKey") !== false) {
				$payload = array("reqId" => "1", "cmd" => $command);
				if($params !== false) {
					$payload["params"] = $params;
				}
				$payload = json_encode($payload);
				$date = gmdate("D, d M Y H:i:s")." GMT";
				$data = implode("\n", array(strtolower($this->ReadPropertyString("SerialNumber")), $date, $payload));
				$hmac = hash_hmac("sha256", $data, $this->ReadPropertyString("SecretKey"));
				$headers = array(
		    	"Date: ".$date,
		    	"Authorization: NEATOAPP ".$hmac
				);
				$result = $this->requestKobold($this->ReadPropertyString("BaseURL").$this->ReadPropertyString("SerialNumber")."/messages", $payload, "POST", $headers);
			}
			return $result;
	}
	/*
		* VR200 Api.
		* Helper class to make requests against Kobold API
		*
		* PHP port based on https://github.com/kangguru/botvac
		*
		* Author: Tom Rosenback tom.rosenback@gmail.com  2016
		*/
	private static function requestKobold($url, $payload = array(), $method = "POST", $headers = array()) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			if($method == "POST") {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			}
			$requestHeaders = array(
				'Accept: application/vnd.neato.nucleo.v1'
			);
			if(count($headers) > 0) {
				$requestHeaders = array_merge($requestHeaders, $headers);
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
			$result = curl_exec($ch);
			curl_close($ch);
			return json_decode($result, true);
	}


 }