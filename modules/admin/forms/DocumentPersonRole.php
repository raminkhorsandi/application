<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Application
 * @package     Module_Admin
 * @author      Jens Schwidder <schwidder@zib.de>
 * @copyright   Copyright (c) 2008-2013, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

/**
 * Unterformular für die Personen in einer bestimmten Role für ein Dokument.
 */
class Admin_Form_DocumentPersonRole extends Admin_Form_AbstractDocumentSubForm {
    
    const ELEMENT_ADD = 'Add';
    
    const ELEMENT_UP = 'MoveUp';
    
    const ELEMENT_DOWN = 'MoveDown';
    
    const ELEMENT_FIRST = 'MoveFirst';
    
    const ELEMENT_LAST = 'MoveLast';
        
    private $__roleName;

    public function __construct($roleName, $options = null) {
        $this->__roleName = $roleName;

        // __construct ruft init Funktion auf
        parent::__construct($options);
    }
    
    public function init() {
        parent::init();
        
        $this->setLegend($this->__roleName);

        // Button zum Hinzufügen von Personen in Role
        $element = new Zend_Form_Element_Submit(self::ELEMENT_ADD);
        $element->setOrder(1000); // TODO not 100% safe
        $this->addElement($element);
    }
    
    public function populateFromModel($document) {
        $persons = $this->_getPersonsInRole($document, $this->__roleName);
        
        foreach ($persons as $index => $person) {
            $this->_addPersonSubForm($index, $person);
        }
    }
    
    public function constructFromPost($post) {
        foreach ($post as $key => $person) {
            if (is_array($person)) {
                $this->_addSubFormFromPost($key, $person);
            }
        }
    }

    public function processPost($post, $context) {
        // Prüfe, ob hinzufügen geklickt wurde
        if (array_key_exists(self::ELEMENT_ADD, $post)) {
            // Hinzufuegen wurde ausgewaehlt
            return array( 'result' => Admin_Form_Document::RESULT_SWITCH_TO, 
                'target' => array(
                'module' => 'admin',
                'controller' => 'person',
                'action' => 'assign',
                'role' => $this->__roleName)
            );
        }
        else {
            // Reiche POST an Personenformulare weiter
            foreach ($post as $subFormName => $personPost) {
                $subform = $this->getSubForm($subFormName);
                if (!is_null($subform)) {
                    $result = $subform->processPost($personPost, $context);
                    if (!is_null($result)) {
                        switch ($result) {
                            case Admin_Form_DocumentPerson::RESULT_REMOVE:
                                $this->removeSubForm($subform->getName());
                                break;
                            default:
                                $result['target']['role'] = $this->__roleName;
                                return $result;
                                break;
                        }
                    }
                }
                else {
                    // TODO log bad POST warning
                }
            }
        }
    }

    /**
     * @param Opus_Document $model
     */
    public function updateModel($model) {
    }
    
    /**
     * 
     * @param type $document
     * 
     * TODO Personen entfernen
     * TODO SortOrder
     */
    public function getPersons($document) {
        $subforms = $this->getSubForms();
        
        $persons = array();
        
        foreach($subforms as $name => $subform) {
            $person = $subform->getLinkModel($document->getId(), $this->__roleName); // TODO should return Link Objekt
            $persons[] = $person;
        }
        
        return $persons;
    }

    protected function _addSubFormFromPost($subFormName, $person) {
        $subform = new Admin_Form_DocumentPerson();
        $subform->initRendering();
        
        $subform->populateFromPost($person);

        $this->_addControls($subform);
        
        $this->addSubForm($subform, $subFormName);
        
        return $subform;
    }
    
    protected function _addPersonSubForm($index, $person) {
        // TODO add subform for person
        $subform = new Admin_Form_DocumentPerson();
        $subform->initRendering();
        $subform->populateFromModel($person);
        
        $this->_addControls($subform);
        
        $this->addSubForm($subform, 'Person' . $index);
        
        return $subform;
    }
    
    protected function _addControls($subform) {
        $element = new Zend_Form_Element_Submit(self::ELEMENT_FIRST);
        $element->setDecorators(array('ViewHelper'));
        $subform->addElement($element);

        $element = new Zend_Form_Element_Submit(self::ELEMENT_UP);
        $element->setDecorators(array('ViewHelper'));
        $subform->addElement($element);
        
        $element = new Zend_Form_Element_Submit(self::ELEMENT_DOWN);
        $element->setDecorators(array('ViewHelper'));
        $subform->addElement($element);
        
        $element = new Zend_Form_Element_Submit(self::ELEMENT_LAST);
        $element->setDecorators(array('ViewHelper'));
        $subform->addElement($element);
    }
    
    /**
     * Liefert die Personen eines Dokuments in einer bestimmten Role zurück.
     * 
     * @param Opus_Document $document
     * @param string $roleName
     * 
     * TODO wenn getPersonXXX Funktionen abgeschafft werden, muss diese Funktion umgeschrieben werden
     */
    protected function _getPersonsInRole($document, $roleName) {
        $fieldName = 'Person' . ucfirst($roleName);
        
        $field = $document->getField($fieldName);
        
        $persons = $field->getValue();
        
        return $persons;
    }
    
    public function continueEdit($request) {
        $role = $request->getParam('role', null);
        
        if (!is_null($role) && $role == $this->__roleName) {
            $personId = $request->getParam('person', null);
            
            $action = $request->getParam('continue', null);

            if (!is_null($personId) && $action !== 'updateperson') {
                $person = new Opus_Person($personId);

                $subform = $this->_addPersonSubForm(count($this->getSubForms()), $person);
                $subform->getElement(Admin_Form_DocumentPerson::ELEMENT_ROLE)->setValue($role);
                
                $order = $request->getParam('order', null);
                $subform->getElement(Admin_Form_DocumentPerson::ELEMENT_SORT_ORDER)->setValue($order);
                
                $allow = $request->getParam('contact', null);
                $subform->getElement(Admin_Form_DocumentPerson::ELEMENT_ALLOW_CONTACT)->setValue($allow);
                
                $subform->getElement(Admin_Form_Person::ELEMENT_PERSON_ID)->setValue($personId);
            }
            else {
                // TODO deal with it
            }
        }
    }
    
    public function isEmpty() {
        return count($this->getSubForms()) == 0;
    }

}