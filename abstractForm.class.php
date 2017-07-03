<?php
abstract class abstractForm {

	private $fields = array();
	private $id;
	private $isNew;
	private $secretName;
	private $submitName;
	private $submitValue;
	private $formId;

	protected abstract function get($name);
	protected abstract function update($name, $value);
	protected abstract function addNew();
	protected abstract function checkIfFailed($name, $value);
	protected abstract function onSuccess();

	function __construct($id, $submitText, $isNew = false) 
	{ 
		$this->id = $id;  
		$this->formId = 'fo' . $this->id;
		$this->isNew = $isNew;
		$this->submitName = 'fo' . $this->id . '-submitted';
		$this->submitValue = $submitText;
		$this->secretName = 'fo' . $this->id . '-secret';
	}

	function __destruct() 
	{ 
	}

	protected function isSubmitted()
	{
		if ( isset($_POST[$this->submitName]) ) {
			return true;
		}
		return false;
	}

	public function addInput($type, $name, $label, $css, $extra = '')
	{
		/*
		* Available extra array fields:
		* 'options'[] => array('display'=>,'value'=>) -- require for select (list of options)
		* 'placeholder' t/f => weather to use placeholder for text/password
		* 'default' => default value (if applicable)
		* 'onclick' => java on click
		* 'onchange' => java on change
		* 'disabled' => t/f weather the field should be disabled
		*/
		$i = count($this->fields);
		$this->fields[$name]['name'] = $name;
		$this->fields[$name]['label'] = $label;
		$this->fields[$name]['type'] = $type;
		$this->fields[$name]['extra'] = $extra;
		$this->fields[$name]['failed'] = false;
		$this->fields[$name]['css'] = $css;
		$this->fields[$name]['hash'] = $this->formId . $name;
		return $this->fields[$name]['hash'];
	}

	protected function getFormId()
	{
		return $this->formId;
	}

	protected function getStartForm()
	{
		global $cPage;
		$newSecret = rand(0,2147483647); // 0  -  2^31-1
		$cPage->sesMan->SetValue($this->secretName, $newSecret);
		$ret = '<form id="'.$this->formId.'" method="post" action="">';
		$ret .= '<input type="hidden" name="'.$this->formId.'" value="'.$newSecret.'">';
		return $ret;
	}

	protected function getSubmitName()
	{
		return $this->submitName;
	}
	
	protected function getSubmitValue()
	{
		return $this->submitValue;
	}

	protected function getValue($name)
	{
		if ( isset($this->fields[$name]['value']) ) {
			return $this->fields[$name]['value'];
		} else {
			throw new Exception('Value is not set.');
		}
	}

	protected function getHash($name)
	{
		if ( isset($this->fields[$name]['hash']) ) {
			return $this->fields[$name]['hash'];
		} else {
			throw new Exception('Value is not set.');
		}

	}

	protected function getEndForm()
	{
		return '</form>';
	}

	protected function getSubmit()
	{
		return '<input type="submit" id="'. $this->submitName.'" name="' . $this->submitName . '" value="' . $this->submitValue . '">';
	}

	protected function getDrawElement($name) 
	{
		$field = $this->fields[$name];
		$ret['CSS'] = $this->genCSS($field['css'], $field['hash']);
		$ret['Body'] = '<div id="DIV'.$field['hash'].'" class="'. ($field['failed']!==false?$this->getFormId().'failed':'') .'">';
		switch ($field['type']) {
		case 'password':
		case 'text':
			if ( isset($field['extra']['placeholder']) && $field['extra']['placeholder'] == true ) {
				$ret['Body'] .= '<input' 
				. ' placeholder="'.$field['label'].'"';
			} else {
				$ret['Body'] .= '<label for="'.$field['hash'].'" id="LABEL'.$field['hash'].'">'.$field['label'].'</label>'
				. '<input';
			}
			$ret ['Body'] .= ' id="'.$field['hash'].'"'
				. ' type="' . $field['type'] . '"' 
				. ' name="'.$field['hash'].'"' 
				. ' value="';
				if ( $field['type'] != 'password' ) {
					if ( isset($_POST[$field['hash']]) ) {
						$ret['Body'] .= $_POST[$field['hash']];
					} else {
						if ( isset($field['extra']['default']) ) {
							$ret['Body'] .= $field['extra']['default'];
						}
					}
				}
			$ret['Body'] .= '"';
			if ( isset($field['extra']['onchange']) ) {
				$ret['Body'] .= ' onChange=\'' . $field['extra']['onchange'] .'\'';
			}
			if ( isset($field['extra']['onclick']) ) {
				$ret['Body'] .= ' onClick=\'' . $field['extra']['onclick'] .'\'';
			}
			if ( isset($field['extra']['disabled']) && $field['extra']['disabled'] == true ) {
				$ret['Body'] .= ' disabled';
			}
			$ret['Body'] .= '>';
			break;
				
		case 'select':
			$ret['Body'] .= '<label for="'.$field['hash'].'">'.$field['label'].'</label>';
			$ret['Body'] .= '<select id="'.$field['hash'].'" name="'.$field['hash'].'"';
			if ( isset($field['extra']['onchange']) ) {
				$ret['Body'] .= ' onChange=\'' . $field['extra']['onchange'] .'\'';
			}
			if ( isset($field['extra']['onclick']) ) {
				$ret['Body'] .= ' onClick=\'' . $field['extra']['onclick'] .'\'';
			}
			if ( isset($field['extra']['disabled']) && $field['disabled'] == true ) {
				$ret['Body'] .= ' disabled';
			}
			$ret['Body'] .= '>';

			$sel = null;
			
			if ( isset($_POST[$field['hash']]) ) {
				$sel= $_POST[$field['hash']];
			} else {
				if ( isset($field['extra']['default']) ) {
					$sel = $field['extra']['default'];
				}
			}
			if ( !isset($field['extra']['options']) ) {
				throw new Exception ('extra["options"] ["display"] and ["value"] has to be set!');
			}
			foreach ( $field['extra']['options'] as $option ) {
				$ret['Body'] .= '<option value="'.$option['value'].'"'.($sel==$option['value']?' selected':'').'>'.$option['display'].'</option>';
			}
			$ret['Body'] .= '</select>';
			break;

		case 'button':
			$ret['Body'] .= '<button id="'.$field['hash'].'"';
			if ( isset($field['extra']['onchange']) ) {
				$ret['Body'] .= ' onChange=\'' . $field['extra']['onchange'] .'\'';
			}
			if ( isset($field['extra']['onclick']) ) {
				$ret['Body'] .= ' onClick=\'' . $field['extra']['onclick'] .'\'';
			}
			if ( isset($field['extra']['disabled']) && $field['disabled'] == true ) {
				$ret['Body'] .= ' disabled';
			}
			$ret['Body'] .= '>' . $field['label'] . '</button>';
			break;

		case 'checkbox':
			$ret['Body'] .= '<input'
					.' type="checkbox"'
					.' name="'.$field['hash'].'"'
					.' id="'.$field['hash'].'"';
					if ( isset($_POST[$this->submitName]) ) {
						if ( isset($_POST[$field['hash']]) && $_POST[$field['hash']] == 'on' ) {
							$ret['Body'] .= ' checked';
						}
					} else {
						if ( isset($field['extra']['default']) && $field['extra']['default'] == 'on' ) {
							$ret['Body'] .= ' checked';
						} 
					}
			if ( isset($field['extra']['onchange']) ) {
				$ret['Body'] .= ' onChange=\'' . $field['extra']['onchange'] .'\'';
			}
			if ( isset($field['extra']['onclick']) ) {
				$ret['Body'] .= ' onClick=\'' . $field['extra']['onclick'] .'\'';
			}
			if ( isset($field['extra']['disabled']) && $field['disabled'] == true ) {
				$ret['Body'] .= ' disabled';
			}
			$ret['Body'] .= '>';
			$ret['Body'] .= '<label for="'.$field['hash'].'">'.$field['label'].'</label>';
			break;
		
		case 'hidden':
			if ( !isset($field['extra']['default']) ) {
				throw new Exception('Hidden requires default field.');
			}
			$ret['Body'] .= '<input type="hidden" name="'.$field['hash'].'" value="'.$field['extra']['default'].'">';
			break;

		default:
			throw new Exception('ABS: Unknown type: ' . $field['type']);
		}
		$ret['Body'] .= '<div class="'.$this->getFormId().'failedText">'.($field['failed']!==false?$field['failed']:'').'</div>';
		$ret['Body'] .= '</div>';
		return $ret;
	}

	public function getDraw()
	{
		$ret['Body'] = '';
		$ret['CSS'] = '.'.$this->getFormId().'failed { background-color: #F33; } ';
		$ret['Body'] .= $this->getStartForm();
		foreach ($this->fields as $field) {
			$el = getDrawElement($field['name']);
			$ret['Body'] .= $el['Body'];
			$ret['CSS'] .= $el['CSS'];
		}
		$ret['Body'] .= $this->getSubmit();
		$ret['Body'] .= $this->getEndForm();
		return $ret;
	}

	public function checkAndUpdate()
	{
		global $cPage;
		$this->hasFailed = false;
		if ( !isset($_POST[$this->submitName]) 
		|| !$cPage->sesMan->HasValue($this->secretName)
		|| !isset($_POST[$this->formId])
		|| $_POST[$this->formId] != $cPage->sesMan->GetValue($this->secretName) ) {
			$this->hasFailed = true;
			$cPage->sesMan->UnsetValue($this->secretName);
			return false;
		}
		$cPage->sesMan->UnsetValue($this->secretName);
		foreach ( $this->fields as &$field ) {
			if ( isset($_POST[$field['hash']]) ) {
				$field['value'] = $_POST[$field['hash']];
			} else {
				if ( $field['type'] != 'checkbox' ) {
					$field['value'] = ''; 
				} else {
					$field['value'] = 'off';
				}
			}
			if ( ($field['failed']=$this->checkIfFailed($field['name'], $field['value'])) !== false ) {
				$this->hasFailed = true;
			}
		}
		if ( $this->hasFailed ) {
			return false;
		}
		$this->onSuccess(); // jakis redirect ? //
		return true;
	}

	private function genCSS($css, $hash)
	{
		return str_replace('[fieldHash]',$hash,$css);
	}
}
