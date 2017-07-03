<?php

if ( !defined('C_editMetadataForm') ) {
define('C_editMetadataForm', true);

include 'abstractForm.class.php';
define('FORM_NEWANALYTE', -2);
define('FORM_SELECT', -1);

class editAnalyteForm extends abstractForm 
{

	private $analyteId;
	private $theData;
	private $nrOfCurves;
	private $concMultiply;
	private $currentMultiply;
	private $JS;
	private $deleteSecret;

	function __construct( &$theData, $analyteId )
	{
		$this->theData =& $theData;
		$this->analyteId = $analyteId;
		global $cPage;
		foreach ($theData['Mesdata'] as &$md) {
			$this->nrOfCurves = $md['nr_of_curves'];
			break;
		}
	

		if ( $analyteId === false ) {
			parent::__construct( 'newMesdata', 'Submit', true );
		} else {
			parent::__construct( 'editMesdata'.$this->analyteId, 'Update', false );
		}
		//die('Meta_id:' . $this->theData['Meta_id']);

		/*
		* Add new analyte field (default hidden)
		*/
		if ( $this->isSubmitted() && isset($_POST[$this->getFormId() . 'addNewAnalyte']) ) {
			$idOfAddNew = $this->addInput('text', 'addNewAnalyte', 'New analyte', '#[fieldHash] { display: block }', array( 'disabled' => false, 'placeholder' => true ) );
		} else {
			$idOfAddNew = $this->addInput('text', 'addNewAnalyte', 'New analyte', '#[fieldHash] { display: none }', array( 'disabled' => true, 'placeholder' => true ) );
		}
		/*
		* DONE
		*/

		/*
		* Add select Analyte
		*/
		$analytes = $cPage->db->query('SELECT DISTINCT name FROM ' . T_ANALYTES, true);
		$extraA['onclick'] = '';
		$extraA['onchange'] = 'checkChangeOf'.$this->getFormId().'(this,"' . $idOfAddNew. '");';
		$extraA['options'][] = array( 
				'display' => '--NEW--', 
				'value' => FORM_NEWANALYTE 
				);
		if (isset($analytes[0]) ) {
			foreach ( $analytes as $t ) {
				if ( $t['name'] == '' ) {
					continue;
				}
				$extraA['options'][] = array( 'display'=> $t['name'], 'value' => $t['name'] );
			}
		}
		$extraA['options'][] = array(
				'display' => 'Select',
				'value' => FORM_SELECT
				);
		$extraA['default'] = FORM_SELECT;
		$this->addInput( 'select', 'selectAnalyte', 'Analyte:', '', $extraA);
		/*
		* DONE
		*/

		/*
		* Add select Units
		*/
		$conc_units = $cPage->db->get(T_CONC_UNITS, 'true');
		$extraU['options'][] = array( 'display' => 'Select', 'value' => FORM_SELECT );
		foreach ($conc_units as $u) {
			$extraU['options'][] = array( 
				'display' => $u['name'],
				'value' => $u['id']
				);
			$this->concMultiply[$u['id']] = $u['multiply_to_mg_L'];
		}
		if ( $analyteId !== false ) {
			if ( isset($theData['Analytes'][$analyteId]['Disp_units_id']) )  {
				$extraU['default'] = $theData['Analytes'][$analyteId]['Disp_units_id'];
				$this->currentMultiply = $theData['Analytes'][$analyteId]['Disp_units_id'];
			}
		}
		$this->addInput( 'select', 'selectUnits', 'Concentration units: ', '', $extraU);
		/*
		* DONE
		*/
		
		/*
		* Add for concentration field for each curve //
		*/
		$cssInp = ' #[fieldHash] { width: 60px; } ';
		for ($i=0; $i<$this->nrOfCurves; $i++) {
			if ( $analyteId === false ) {
				$this->addInput('text', ('curve' . $i), ('#'.($i+1).': '), $cssInp);
			} else {
				$conc_in_mg_L = $theData['Analytes'][$this->analyteId]['Conc'][$i];
				$this->addInput('text', ('curve' . $i), ('#'.($i+1).': '), $cssInp, array('default' => $this->getConcInDisplayUnits($conc_in_mg_L)) );
			}
		}
		/*
		* DONE
		*/

		/*
		* Add delete button
		*/
		if ( !$cPage->sesMan->HasValue($this->getFormId() . 'deleteSecret') ) {
			$this->deleteSecret = mt_rand(0, 10000);
			$cPage->sesMan->SetValue($this->getFormId() . 'deleteSecret', $this->deleteSecret);
		} else {
			$this->deleteSecret = $cPage->sesMan->GetValue($this->getFormId() . 'deleteSecret');
		}
		$cssBut = '#[fieldHash] { float: right; }';
		$button_hash = $this->addInput( 'button', 'deleteAnalyte', 'Delete', $cssBut, array('onclick'=>'deleteAnalyte'.$this->getFormId().'("'.parent::getFormId().'");') );
		/*
		* DONE
		*/

		$this->addInput( 'hidden', 'details_meta_id', '','', array('default'=>$this->theData['Meta_id']));

		$this->JS = '
function deleteAnalyte'.$this->getFormId().'(formId) 
{ 
	if ( confirm("Are you sure to delete the data?") ) {
		var input = $("<input>")
               		.attr("type", "hidden")
               		.attr("name", "'.$this->getHash('deleteAnalyte').'").val("'.$this->deleteSecret.'");
		$("#'.$this->getFormId().'").append($(input));
		var subb = $("<input>")
			.attr("type", "hidden")
			.attr("name", "'.$this->getSubmitName().'").val("'.$this->getSubmitValue().'");
		$("#'.$this->getFormId().'").append($(subb));
	} 
};'
.'function checkChangeOf'.$this->getFormId().'(src,trgid) { trg = document.getElementById(trgid); if ( src.options[src.selectedIndex].value == "'.FORM_NEWANALYTE.'" ) { trg.disabled=false; trg.style.display=\'block\'; } else { trg.disabled=true; trg.style.display=\'none\'; } }';
	}

	private function getConcInDisplayUnits($value) 
	{
		return ($value / $this->concMultiply[$this->currentMultiply]);
	}

	private function getConcInDBUnits($value)
	{
		return ($value * $this->concMultiply[$this->getValue('selectUnits')]);
	}

	function get($name)
	{
	}

	function getDraw()
	{
		$ret['CSS'] = ' body {background-color: #AAA} '
				.'.' . $this->getFormId() . 'failed { background-color: #FF8888; }'
				.'.' . $this->getFormId() . 'failedText { font-size: 10px; }'; 
		$ret['Body'] = $this->getStartForm();
		$hd = $this->getDrawElement('details_meta_id');
		$ret['Body'] .= $hd['Body'];
		$ret['Body'] .= '<fieldset><legend>';
		if ( $this->analyteId !== false ) {
			$ret['Body'] .= 'Analyte: ' . $this->theData['Analytes'][$this->analyteId]['Name'];
		} else {
			$sa = $this->getDrawElement('selectAnalyte');
			$na = $this->getDrawElement('addNewAnalyte');
			$ret['Body'] .= 'New ' . $sa['Body'] . $na['Body'];
			$ret['CSS'] .= $sa['CSS'];
			$ret['CSS'] .= $na['CSS'];
		}
		$ret['Body'] .= '</legend>';
		$un = $this->getDrawElement('selectUnits');
		$ret['Body'] .= $un['Body'];
		$ret['CSS'] .= $un['CSS'];
		$ret['Body'] .= 'Concentration value:<br>';
		for ( $i=0; $i<$this->nrOfCurves; $i++ ) {
			$el = $this->getDrawElement('curve' . $i); 
			$ret['Body'] .= $el['Body'];
			$ret['CSS'] .= $el['CSS'];
		}
		$ret['Body'] .= '<div id="'.$this->getFormId().'buttons">';
		$ret['Body'] .= $this->getSubmit();
		if ( $this->analyteId !== false ) {
			$del = $this->getDrawElement('deleteAnalyte');
			$ret['Body'] .= $del['Body'];
			$ret['CSS'] .= $del['CSS'];
		}
		$ret['Body'] .= '</div>'; //id buttons
		$ret['Body'] .= '</fieldset>';
		$ret['Body'] .= $this->getEndForm();
		$ret['Scripts'] = $this->JS;
		$ret['CSS'] .= ' #'.$this->getSubmitName() .' { float: left; } #'.$this->getFormId().'buttons { margin-top: 10px; width: 190px; } ';
		return $ret;
	}

	function checkIfFailed($name, $value)
	{
		global $cPage;
		if ( substr($name,0,5) == 'curve' ) {
			$name = 'curve';
		}
		switch ($name) {
		case 'addNewAnalyte':
			if  ( $this->analyteId !== false ) {
				return false;
			}
			if ( isset($_POST[$this->getFormId() . 'selectAnalyte']) 
			&& $_POST[$this->getFormId() . 'selectAnalyte'] == FORM_NEWANALYTE ) {
				if ( trim($value) == '' ) {
					return 'New analyte cannot be empty.';
				} else if ( is_numeric($value) ) {
					return 'Name cannot contain only numbers.';
				} else if ( isset($this->theData['Analytes']) && is_array($this->theData['Analytes']) ) {
					foreach ( $this->theData['Analytes'] as $an ) {
						if ( $value == $an['Name'] ) {
							return 'Analyte is already set for this data.';
						}
					}
				}
			}
			return false; 

		case 'selectAnalyte':
			if  ( $this->analyteId !== false ) {
				return false;
			}
			if ( $value == FORM_SELECT ) {
				return 'Please select analyte or add new.';
			} else if ( $value != FORM_NEWANALYTE 
			&& isset($this->theData['Analytes']) 
			&& is_array($this->theData['Analytes']) ) {
				foreach ( $this->theData['Analytes'] as $an ) {
					if ( $value == $an['Name'] ) {
						return 'Analyte is already set for this data.';
					}
				}
			}
			return false;

		case 'selectUnits':
			if ( $value == FORM_SELECT ) {
				return 'Please select concentration units.';
			}
			if ( !is_numeric($value) ) {
				return 'Wrong selection.';
			}
			return false;

		case 'deleteAnalyte': 
			if ( $this->analyteId !== false ) {
				if ( $cPage->sesMan->HasValue($this->getFormId() . 'deleteSecret') ) {
					$secret = $cPage->sesMan->GetValue($this->getFormId() . 'deleteSecret');
					if ( $secret == $value ) { // if this is hit, nothing else will be checked ... //
						$cPage->sesMan->UnsetValue($this->getFormId() . 'deleteSecret');
						$cPage->dh->deleteDBAnalyte($this->analyteId);
						$cPage->setNotice('Data successfuly deleted.', OK);
						$cPage->reloadPage();
					}
				} 
				
			}
			return false;

		case 'curve':
			if ( !is_numeric($value) ) {
				return 'Concentration has to be numeric.';
			}
			return false;

		case 'details_meta_id':
			return false;

		default:
			throw new Exception('missing.');
		}
		return false; 
	}

	function update($name, $value)
	{
		return;
	}

	function addNew()
	{
		return true;
	}

	protected function onSuccess()
	{
		global $cPage;
		if ( !isset($_GET['metaid']) 
		|| $_GET['metaid'] != $this->getValue('details_meta_id') ) {
			throw new formError('New form expired. details: ' . $this->getValue('details_meta_id') . ' GET: ' . $_GET['metaid'] );
		} 

		if ( !$cPage->dh->isLoaded() )
			$cPage->dh->loadStruct($_GET['metaid']);
		$theData =& $cPage->dh->getStruct();
		$tmp = reset($theData['Mesdata']);
		$nr_of_cur = $tmp['nr_of_curves'];
		unset($tmp);
		$i=1;
		$conc = array();
		for ( $i=0; $i<$this->nrOfCurves; $i++ ) {
			$conc[$i] = $this->getConcInDBUnits($this->getValue('curve'.$i));
		}
		
		if  ( $this->analyteId === false ) {
			if ( $this->getValue('selectAnalyte') == FORM_NEWANALYTE ) {
				$analyteName = $this->getValue('addNewAnalyte');
			} else {
				$analyteName = $this->getValue('selectAnalyte');
			}
		}
		if ( !$cPage->dh->isLoaded() ) {
			die('not loaded');
			$cPage->dh->loadStruct($_GET['metaid']);
		}
		if ( $this->analyteId !== false && isset($theData['Analytes'][$this->analyteId]) ) {
			$an = array(
				'Conc' => $conc,
				'Disp_units_id' => $this->getValue('selectUnits'),
				);
			if ( !$cPage->dh->updateDBAnalyte($this->analyteId, $an) ) {
				$cPage->setNotice('There has been an error, analyte not updated.', ERROR);
			}
		} else {
			$an=array(
				'Name' => $analyteName,
				'Conc' => $conc,
				'Disp_units_id' => $this->getValue('selectUnits')
				);
			if ( !is_numeric($cPage->dh->addDBAnalyte($an)) ) {
				$cPage->setNotice('There has been an error, analyte not added.', ERROR);
			}
		}
		$cPage->setNotice('Data updated at ' . date('Y-m-d H:i:s e'), OK);
		$cPage->reloadPage();
	}
}

}//END IF DEFINED
