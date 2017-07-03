<?php

class FileUploader {

	protected $fileUploadAttempt, $fileUploadStatus, $userFileContent, $form, $firstIsE, $items, $voltType, $isCV, $printDetailsPage, $meta_id, $mes_id;
	public $drawPlotId, $cPage;

	function __construct($parent) {
		register_shutdown_function(array($this, '__destruct'));
		$this->cPage = mainPage::getInstance();
		$this->voltType = 'NOT_SET';
		$this->isCV = NULL;
		$this->printDetailsPage = false;
		if ( isset($_POST['upload_file']) ) {
			$this->fileUploadAttempt = true;
		} else {
			$this->fileUploadAttempt = false;
		}
	}

	function __destruct() {
		if ( isset($_FILES) ) {
			foreach ($_FILES as $f) {
				@unlink($f['tmp_name']);
			}
		}
	}

	function Check() {
		$extra = '';

		if ( $this->cPage->sesMan->isLoggedAs() === false )
			throw new Exception('User is not logged in, cannot upload file.');

		if ( (isset($_GET['action']) && $_GET['action'] == 'details') && (isset($_GET['id']) && is_numeric($_GET['id'])) && isset($_POST['confirmDetails']) )
			$this->updateDetails();

		if ( $this->fileUploadAttempt == true ) {
			try {
				if ( is_file($_FILES['datafile']['tmp_name']) ) {
					$expFileName = explode('.',mb_strtolower($_FILES['datafile']['name']));
					if ( end($expFileName) != 'vol' ) {
						if ( isset($_POST['1isE']) && $_POST['1isE'] == '1' ) {
							$this->firstIsE = true;
						} else {
							$this->firstIsE = false;
							if ( !isset($_POST['to_E'])
								|| !isset($_POST['from_E'])
								|| !is_numeric($_POST['to_E'])
								|| !is_numeric($_POST['from_E']) )
								throw new Exception('Unknown potential range, cannot continue.');
						}
						if ( !isset($_POST['voltType']) || $_POST['voltType'] == 'NOT_SET' ) {
							throw new Exception('Technika woltamperometryczna musi być wybrana.');
						} else {
							$sel = $this->cPage->db->getEnumValues(T_METADATA, 'voltemmetry_type');
							if ( !in_array($_POST['voltType'],$sel) ) {
								throw new Exception('Not allowed voltType');
							}
							$this->voltType = $_POST['voltType'];
						}
					}
					if ( isset($_POST['isCV']) && ( $_POST['isCV'] == 1 || $_POST['isCV'] == 'on' ) )
						$this->isCV = 1;

					$hd = fopen($_FILES['datafile']['tmp_name'], 'r');
					$this->userFileContent = fread($hd,filesize($_FILES['datafile']['tmp_name']));
					fclose($hd);
					unlink($_FILES['datafile']['tmp_name']);
					if ( strlen($this->userFileContent) <= 1 )
						throw new Exception('Upload Failed');
					$this->userFileContent=$this->dataExtractor();
					$_filename = $_FILES['datafile']['name'];
					if ( isset($_POST['doAVG']) && is_numeric($_POST['doAVG']) && $_POST['doAVG'] > 1 && $_POST['doAVG'] < 99 ) {
						if ( $this->tryToAVG()!= false )
							$_filename = $_FILES['datafile']['name'] . ' (averege n=' . $_POST['doAVG'] . ')';
						
					}
					$this->fileUploadStatus = 'OK';

					$doesexists = $this->cPage->db->get(T_METADATA,'owner_id= ' . $this->cPage->sesMan->isLoggedAs() .' AND file_name="'. $_filename .'"');
					$is_duplicate = false;
					if ( isset($doesexists[0]) ) {
						foreach ($doesexists as $de) {
							$compare = $this->cPage->db->get(T_MESDATA,'meta_id=' . $de['id'] .' AND process_id=0');
							if ( isset($compare[0]) ) {
								if ( $compare[0]['data'] == serialize($this->userFileContent) ) {
									$is_duplicate = $de['id'];
									break;
								}
							}
						}
					}
					if ( $is_duplicate !== false ) {
						$this->cPage->setNotice('File already exists with id: ' . $is_duplicate, NOTICE);
						$this->meta_id = $is_duplicate;
						$this->mes_id = $compare[0]['id'];
						$this->printDetailsPage = true;
						return;
					}

					$nds = array(
						'File_name' => $_filename,
						'isCV' => $this->isCV,
						'voltType' => $this->voltType,
						'Mesdata' => array( 
								0 => array (
									'Data' => $this->userFileContent,
									)
								),
						'Analytes' => NULL
							);

					$this->cPage->dh->addNewStruct($nds);
					$this->meta_id = $this->cPage->dh->getMetaId();
					$this->mes_id = $this->cPage->dh->getRawMesId();
					header('Location: ' . $this->cPage->dh->getEditDataURL($this->meta_id, $this->mes_id));
				} else {
					//FILE not found
					throw new Exception('File upload error');
				}
			} catch (Exception $e) {
				$this->fileUploadStatus = 'FAILED';
				print_r($e);
			}
		}
	}

	function tryToAVG()
	{
		$avg = $_POST['doAVG'];
		$cols = count($this->userFileContent['Y']);
		$rows = count($this->userFileContent['Y'][0]);
		if ( ($cols % $avg) != 0 ) {
			$this->cPage->setNotice('Nie można uśrednić po ' . $avg . ' kolumn.', ERROR);
			return false;
		}
		for ( $i=0;$i<$cols;$i=$i+$avg ) {
			for ($r=0;$r<$rows;$r++) {
				$sumy = 0;
				$sumx = 0;
				for ($ii=0;$ii<$avg;$ii++) {
					if ( !isset($this->userFileContent['Y'][$i+$ii][$r]) )
						return false;
					$sumy = $sumy + $this->userFileContent['Y'][$i+$ii][$r];
				}
				$res['Y'][($i/$avg)][$r] = ($sumy/$avg);
				$res['X'][0][$r] = $this->userFileContent['X'][0][$r];
			}
			if ( isset($this->userFileContent['Name'][$i]) )
				$res['Name'][($i/$avg)] = $this->userFileContent['Name'][$i];
		}
		$this->userFileContent['X'] = $res['X'];
		$this->userFileContent['Y'] = $res['Y'];
		if ( isset($this->userFileContent['Name']) )
			$this->userFileContent['Name'] = $res['Name'];
		return true;
	}

	function generateForm()
	{
		$selectors = $this->cPage->db->getEnumValues(T_METADATA, 'voltammetry_type');
		$ret = '<tr style="visibility:hidden" id="Erange"><td colspan=2><sup style="color:red":>*</sup>Pierwsza kolumna pliku zawiera wartości potencjału ?</td><td> <input type="checkbox" name="1isE" value="1" id="1isE" checked onChange="checkWasChanged();"></td></tr><tr id="noE" style="visibility:hidden"><td>Podaj zakres potencjału:</td><td>od <input id="from_E" type="text" name="from_E"> mV</td><td>do <input id="to_E" type="text" name="to_E"> mV</td></tr>';
		$ret .= '<tr id="voltType" style="visibility: hidden"><td colspan=2>Technika pomiarowa: <select name="voltType">';
		foreach ( $selectors as $s) {
			$ret .= '<option value="' . $s . '"' . ($s=='NOT_SET'?' selected':'') . '>' . $s . '</option>';
		}
		$ret .= '</select></td></tr>';
		$ret .= '<tr id="isCV" style="visibility: hidden"><td colspan=2>Pomiar cykliczny ?: <input type="checkbox" name="isCV"></td></tr>';
		return $ret;
	}

	function Draw() {
		//echo 'STATUS:' . $this->fileUploadStatus;
		$ret['Body'] = $this->UploadForm();
		return $ret;
	}

	function UploadForm()
	{

			$form_looks = '
<script type="text/javascript">
function checkext(field) {
	if (field.type == "file") {
		var _FileExtensions = [".vol"];
		var sFileName = field.value;
		if (sFileName.length > 0) {
			var blnValid = false;
			for (var j = 0; j < _FileExtensions.length; j++) {
			    var sCurExtension = _FileExtensions[j];
			    if (sFileName.substr(sFileName.length - sCurExtension.length, sCurExtension.length).toLowerCase() == sCurExtension.toLowerCase()) {
					blnValid = true;
					break;
			    }
			}
			if ( blnValid == false ) {
				document.getElementById("Erange").style.visibility="visible";
				document.getElementById("voltType").style.visibility="visible";
				document.getElementById("isCV").style.visibility="visible";
			} else {
				document.getElementById("Erange").style.visibility="hidden";
				document.getElementById("voltType").style.visibility="hidden";
				document.getElementById("isCV").style.visibility="hidden";
			}
		}
	}
}
document.getElementById("FileSender").addEventListener("click", function(event){
    event.preventDefault()
});
function sendFile()
{
document.getElementById("FileSender").disabled = true;
document.getElementById("uploader").submit();
}
</script>
				<div class="form"><p>Aby poprawić pracę algorytmu, proszę wypełnić wszystkie pola.<br><sup style="color:red">*</sup> oznacza pole wymagane.</p>
				<form enctype="multipart/form-data" action="?name=uploadfile" method="POST" id="uploader">
				<table>
				<tr><td><input type="hidden" name="upload_file" value="1">
				<sup style="color:red":>*</sup>Plik z danymi:</td><td colspan=2> <input name="datafile" type="file" onchange="checkext(this);"></td></tr>
				<tr><td colspan=2>Uśrednij kolumny po: <input type="text" name="doAVG" value="1" maxlength="2" style="width:30px"> (1 oznacza brak uśredniania)</td></tr>

				' . $this->generateForm() . '
				<tr><td colspan=3><input type="submit" value="Send File" style="width:100%;height:50px;" id="FileSender" onClick="sendFile();"></td></tr></table>

			    	</form></div>';
		return $form_looks;
	}

	function dataExtractor() {
		// try to decode by extension //
		$expFileName = explode('.',mb_strtolower($_FILES['datafile']['name']));
		$numexp = count($expFileName);

		switch($expFileName[$numexp-1]) {

		case 'vol':
			$volLen =  strlen($this->userFileContent);
			$nrCur = unpack('S',substr($this->userFileContent,0,2));
			$nrCur = $nrCur[1];
			//$tmp='';
			for ( $i=0; $i<$nrCur; ++$i ) {
				//unset($tmp);
				$curveName[$i] = substr($this->userFileContent,2+$i*10+($i*2) , 10);
				$curveName[$i] = substr($curveName[$i],0,strpos($curveName[$i],"\0"));
			}

			for ( $pa=0;$pa<60/*il parametrow*/;++$pa) {
				$tmp = unpack('l',substr($this->userFileContent, 2/*il krz*/ + (50*12)/*nazwy*/ + ($pa*4), 4));
				for ( $cn=0;$cn<$nrCur;++$cn )
					$curveParams[$cn][$pa] = $tmp[1];
			}

			for ($ii = 2+$i*10+($i*2);$ii<$volLen; ++$ii) {
				for ( $iii=0;$iii<$nrCur;++$iii) {
					if ( substr($this->userFileContent,$ii,(strlen($curveName[$iii])+1)) == $curveName[$iii] . "\0" )
						$curveStart[$iii] = $ii-2;
				}
			}

			$curValues = array();
			$curValues['Y'] = new splFixedArray($nrCur);
			$curValues['X'] = new splFixedArray(1);

			for ( $i=0;$i<$nrCur;++$i) {
				$readpos = $curveStart[$i];
				$curNums[$i] = unpack('S',substr($this->userFileContent,$readpos, 2)); //numer krzywej
				$readpos += 2 /*z numeru krzywej*/;
				if ( substr($this->userFileContent,$readpos,(strlen($curveName[$i])+1)) != $curveName[$i] . "\0" )
					throw new Exception('Error while reading file');
				$readpos += 10; //nazwa
				$curComments[$i] = substr($this->userFileContent, $readpos, strpos(substr($this->userFileContent,$readpos), "\0")-1);
				$readpos += 50; //komentarz
				$tmp = unpack('S', substr($this->userFileContent, $readpos, 2));
				$readpos += 2;
				$diffpar = $tmp[1];
				$num=0;
				for ( $p=0;$p<$diffpar;++$p) {
					$pNum = unpack('S',substr($this->userFileContent,$readpos,2));
					$readpos += 2;
					$pVal = unpack('l',substr($this->userFileContent,$readpos,4));
					$readpos += 4;
					$curveParams[$i][$pNum[1]] = $pVal[1];
				}
				if ( $i == 0 ) {
					$nrofpoints = $curveParams[$i][16];// 16. parametr to ilość punktów //
					$cyclic = $curveParams[$i][4];
				} else if ( $nrofpoints != $curveParams[$i][16] || $cyclic != $curveParams[$i][4] ) {
					throw new Exception('Measurement paramters have to be the same for all curves in file.');
				}
				if ( $curveParams[$i][4] >= 1 ) {
					$curValues['X'][0] = new splFixedArray(2*$nrofpoints); // 2*if it is CV //
					$curValues['Y'][$i] = new splFixedArray(2*$nrofpoints); // 2*if it is CV //
				} else {
					$curValues['X'][0] = new splFixedArray($nrofpoints);
					$curValues['Y'][$i] = new splFixedArray($nrofpoints);
				}
				$Estart = $curveParams[$i][9];
				$Eend = $curveParams[$i][10];
				$Estep = ($curveParams[$i][10] - $curveParams[$i][9]) / $nrofpoints;
				$num = 0;
				$limit = $readpos+($nrofpoints*8);
				for ( $readpos=$readpos;$readpos<$limit;$readpos+=8 ) {
					$tmp = unpack('d',substr($this->userFileContent,$readpos, 8));
					$curValues['Y'][$i][$num] = $tmp[1];
					$curValues['X'][0][$num] = $Estart + ($num*$Estep);
					$num++;
					if ($num > $nrofpoints) 
						throw new Exception('Exception on readpos: ' . $readpos);
				}
				$pts_1w=$num;
				if ( $curveParams[$i][4] >= 1 /*krzywa cykliczna*/ ) {
					$limit = $readpos+($nrofpoints*8);
					$np = 0;
					if ( isset($mirror) )
						unset($mirror);
					for ( $readpos=$readpos;$readpos<$limit;$readpos+=8 ) {
						$tmp = unpack('d',substr($this->userFileContent,$readpos, 8));
						$mirror[$np]=$tmp[1];
						$np++;
						$curValues['X'][0][$num] = $Eend - (($num-$pts_1w)*$Estep); //TODO: BLAD
						$num++;
						if ($num > 2*$nrofpoints)
							throw new Exception('Exception on readpos: ' . $readpos);
					}
					$nppp = 0;
					for ( $npp=($np-1);$npp>=0;$npp--) {
						$curValues['Y'][$i][$pts_1w+$nppp] = $mirror[$npp];
						$nppp++;
					}
						
				}
			}

			if ( $curveParams[0][4] >=1 )
				$this->isCV = 1;
			else
				$this->isCV = 0;

			switch ($curveParams[0][0]) {
			case 0:
				$this->voltType='SCV';
				break;
			case 1:
				$this->voltType='NPV';
				break;
			case 2:
				$this->voltType='DPV';
				break;
			case 3:
				$this->voltType='SWV';
				break;
			case 4:
				$this->voltType='LSV';
				break;
			default:
				$this->voltType='UNKNOWN';
			}

			$curValues['ParamsFromFile'] = $curveParams;
			$curValues['Names'] = array_map('trim',$curveName);
			$curValues['Comments'] = array_map('trim',$curComments);
			return $curValues;

		case 'csv': //EXTRACT FROM CSV
			$rowed=explode("\n",str_replace("\r",'',$this->userFileContent));
			if ( empty($rowed[count($rowed)-1]) ) // check if last is empty
				unset($rowed[count($rowed)-1]); //delete if is empty
			$csv = array_map('str_getcsv', $rowed); //convert from csv to array each row
			$rows = count($csv);
			$cols = count($csv[0]);
			unset($this->userFileContent); //free memory
			$rawArrayData = new stdClass();
			if ( $this->firstIsE == true && $cols > 1 ) {
				$br=0;
				for ($i=0;$i<$rows;$i++) {
					for ( $t=1;$t<$cols;$t++ )
						$rawArrayData['X'][0][$i] = $csv[$i][0]; //first col should be potential
				}
				for ( $i=1; $i<$cols; $i++ ) {
					for ( $ii=0; $ii<$rows; $ii++ ) {
						$rawArrayData['Y'][$i-1][$ii] = $csv[$ii][$i]; //remaining should be current
					}
				}
			} elseif ( $this->firstIsE == true && $cols < 2) {
				throw new Exception('First col is E, but data has one col');
			} else {
				for ( $i=0;$i<$cols;$i++) {
					for ($ii=0;$ii<$rows;$i++) {
						$rawArrayData['Y'][$i][$ii] = $csv[$ii][$i]; //all cols are current
					}
				}
				if ( $this->isCV === 1 )
					$step = ($_POST['to_E'] - $_POST['from_E']) / 0.5*$rows; //generate potential step
				else
					$step = ($_POST['to_E'] - $_POST['from_E']) / $rows; //generate potential step
				
				$iter = 0;
				for ( $i=$_POST['from_E'];$i<=$_POST['to_E'];$i=$i+$step ) {
					for ($t=0;$t<$cols;$t++)
						$rawArrayData['X'][0][$iter]=$i;//fill in potential 
					$iter++;
				}
				if ( $this->isCV === 1 ) {
					for ( $i=$_POST['to_E'];$i>=$_POST['from_E'];$i=$i-$step ) {
						for ($t=0;$t<$cols;$t++)
							$rawArrayData['X'][0][$iter]=$i;//fill in potential 
						$iter++;
					}
				}
			}

			return $rawArrayData;
			//EXTRACTED FROM CSV

		case 'txt': // EXTRACT from EALAB txt
			$rows=explode("\n",str_replace("\r",'',$this->userFileContent));
			unset($this->userFileContent);
			if ( !isset($rows[0]) )
				throw new Exception('Cant get rows');
				
			if ( substr_count($rows[0],';') > 1 ) {
				$separator = ';';
			} elseif ( substr_count($rows[0],"\t" ) > 1 ) {
				$separator = "\t";
			} elseif ( substr_count($rows[0],' ') > 1 ) {
				$separator = ' ';
			} elseif ( substr_count($rows[0],',') > 1 ) {
				$separator = ',';
			} else {
				throw new Exception('Could not find separator');
			}

			$rawArrayData = new stdClass();
			$numrow = count($rows);
			$hack = 0;

			for ( $i=0;$i<$numrow;$i++) {
				$rows[$i] = trim(preg_replace( "/\s+/", " ", trim($rows[$i]) ));
				$expRow = explode($separator,$rows[$i]);
				$numinrow = count($expRow);
				for ( $ii=0;$ii<$numinrow;$ii++) {
					if ( is_numeric($expRow[$ii]) ) {
						if ( $ii == 0 && $this->firstIsE == true ) {
							for ( $t=1;$t<$numinrow;$t++ )
								$rawArrayData['X'][0][$i] = (float) trim($expRow[$ii]);
						} elseif ( $ii != 0 && $this->firstIsE == true ) {
							$rawArrayData['Y'][$ii-1][$i] = (float) trim($expRow[$ii]);
						} else {
							$rawArrayData['Y'][$ii][$i] = (float) trim($expRow[$ii]);
						}
					}	
				}
				unset($rows[$i]);
			}

			if ( $this->firstIsE == false ) {
				if ( $this->isCV === 1 )
					$step = ($_POST['to_E'] - $_POST['from_E']) / (0.5*count($rawArrayData['Y'][0])); //generate potential step
				else
					$step = ($_POST['to_E'] - $_POST['from_E']) / count($rawArrayData['Y'][0]); //generate potential step
				
				$iter = 0;
				for ( $i=$_POST['from_E'];$i<=$_POST['to_E'];$i=$i+$step ) {
					for ($t=0;$t<$numinrow;$t++)
						$rawArrayData['X'][0][$iter]=$i;//fill in potential 
					$iter++;
				}
				if ( $this->isCV === 1 ) {
					for ( $i=$_POST['to_E'];$i>=$_POST['from_E'];$i=$i-$step ) {
						for ($t=0;$t<$numinrow;$t++)
							$rawArrayData['X'][0][$iter]=$i;//fill in potential 
						$iter++;
					}
				}
			}

			return $rawArrayData;
			// EXTRACTED FROM EALAB TXT

		default:
			throw new Exception('Unhandled extension');
		}
	}
}
