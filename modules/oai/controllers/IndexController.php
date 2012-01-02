<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the North Rhine-Westphalian Library Service Center,
 * the Cooperative Library Network Berlin-Brandenburg, the Saarland University
 * and State Library, the Saxon State Library - Dresden State and University
 * Library, the Bielefeld University Library and the University Library of
 * Hamburg University of Technology with funding from the German Research
 * Foundation and the European Regional Development Fund.
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
 * @package     Module_Oai
 * @author      Felix Ostrowski <ostrowski@hbz-nrw.de>
 * @author      Simone Finkbeiner <simone.finkbeiner@ub.uni-stuttgart.de>
 * @author      Henning Gerhardt <henning.gerhardt@slub-dresden.de>
 * @author      Thoralf Klein <thoralf.klein@zib.de>
 * @copyright   Copyright (c) 2009 - 2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

class Oai_IndexController extends Controller_Xml {

    /**
     * Holds information about which document state aka server_state
     * are delivered out
     *
     * @var array
     */
    private $_deliveringDocumentStates = array('published', 'deleted');  // maybe deleted documents too

    /**
     * Holds restriction types for xMetaDiss
     *
     * @var array
     */
    private $_xMetaDissRestriction = array('doctoralthesis', 'habilitation');

    /**
     * Hold oai module configuration model.
     *
     * @var Oai_Model_Configuration
     */
    protected $_configuration = null;

    /**
     * Gather configuration before action handling.
     *
     * @return void
     */
    public function init() {
        parent::init();

        $registry = Zend_Registry::getInstance();
        $config = $registry->get('Zend_Config');

        $this->_configuration = new Oai_Model_Configuration($config);
    }

    /**
     * Entry point for all OAI-PMH requests.
     *
     * @return void
     */
    public function indexAction() {

        // to handle POST and GET Request, take any given parameter
        $oaiRequest = $this->getRequest()->getParams();
        // remove parameters which are "safe" to remove
        $safeRemoveParameters = array('module', 'controller', 'action', 'role');
        foreach ($safeRemoveParameters as $parameter) {
            if (true === array_key_exists($parameter, $oaiRequest)) {
                unset($oaiRequest[$parameter]);
            }
        }

        try {
            $this->__handleRequest($oaiRequest);
            return;
        }
        catch (Oai_Model_Exception $e) {
            switch ($e->getCode()) {
                case Oai_Model_Error::BADVERB:
                    $errorCode = 'badVerb';
                    break;
                case Oai_Model_Error::BADARGUMENT:
                    $errorCode = 'badArgument';
                    break;
                case Oai_Model_Error::NORECORDSMATCH:
                    $errorCode = 'noRecordsMatch';
                    break;
                    case Oai_Model_Error::CANNOTDISSEMINATEFORMAT:
                    $errorCode = 'cannotDisseminateFormat';
                    break;
                case Oai_Model_Error::BADRESUMPTIONTOKEN:
                    $errorCode = 'badResumptionToken';
                    break;
                default:
                    $errorCode = 'unknown';
            }
            Zend_Registry::get('Zend_Log')->err($errorCode);
            $this->_proc->setParameter('', 'oai_error_code', $errorCode);
            Zend_Registry::get('Zend_Log')->err($e->getMessage());
            $this->_proc->setParameter('', 'oai_error_message', htmlentities($e->getMessage()));
        }
        catch (Oai_Model_ResumptionTokenException $e) {
            Zend_Registry::get('Zend_Log')->err($e);
            $this->_proc->setParameter('', 'oai_error_code', 'unknown');
            $this->_proc->setParameter('', 'oai_error_message', 'An error occured while processing the resumption token.');
            $this->getResponse()->setHttpResponseCode(500);
        }
        catch (Exception $e) {
            Zend_Registry::get('Zend_Log')->err($e);
            $this->_proc->setParameter('', 'oai_error_code', 'unknown');
            $this->_proc->setParameter('', 'oai_error_message', 'An internal error occured.');
            $this->getResponse()->setHttpResponseCode(500);
        }

        $this->_xml = new DomDocument;
    }

    private function getOaiBaseUrl() {
        $oai_base_url = $this->_configuration->getOaiBaseUrl();

        // if no OAI base url is set, use local information as base url
        if (true === empty($oai_base_url)) {
            $request = $this->getRequest();
            $base = $request->getBaseUrl();
            $host = $request->getHttpHost();
            $scheme = $request->getScheme();
            $module = $request->getModuleName();
            $oai_base_url = $scheme . '://' . $host . $base . '/' . $module;
        }

        return $oai_base_url;
    }

    /**
     * Handles an OAI request.
     *
     * @param  array  $oaiRequest Contains full request information
     * @throws Oai_Model_Exception Thrown if the request could not be handled.
     * @return void
     */
    private function __handleRequest(array $oaiRequest) {
        // Setup stylesheet
        $this->loadStyleSheet($this->view->getScriptPath('index') . '/oai-pmh.xslt');

        // Set response time
        $this->_proc->setParameter('', 'dateTime', str_replace('+00:00', 'Z', Zend_Date::now()->setTimeZone('UTC')->getIso()));

        // set OAI base url
        $this->_proc->setParameter('', 'oai_base_url', $this->getOaiBaseUrl());

        $metadataPrefixPath = $this->view->getScriptPath('index') . DIRECTORY_SEPARATOR . 'prefixes';
        $resumptionPath = $this->_configuration->getResumptionTokenPath();

        $request = new Oai_Model_Request();
        $request->setPathToMetadataPrefixFiles($metadataPrefixPath);
        $request->setResumptionPath($resumptionPath);

        if (true !== $request->validate($oaiRequest)) {
            throw new Oai_Model_Exception($request->getErrorMessage(), $request->getErrorCode());
        }

        foreach ($oaiRequest as $parameter => $value) {
            Zend_Registry::get('Zend_Log')->debug("'oai_' . $parameter, $value");
            $this->_proc->setParameter('', 'oai_' . $parameter, $value);
        }

        switch ($oaiRequest['verb']) {
            case 'GetRecord':
                $this->__handleGetRecord($oaiRequest);
                break;

            case 'Identify':
                $this->__handleIdentify($oaiRequest);
                break;

            case 'ListIdentifiers':
                $this->__handleListIdentifiers($oaiRequest);
                break;

            case 'ListMetadataFormats':
                $this->__handleListMetadataFormats($oaiRequest);
                break;

            case 'ListRecords':
                $this->__handleListRecords($oaiRequest);
                break;

            case 'ListSets':
                $this->__handleListSets($oaiRequest);
                break;

            default:
                throw new Exception('The verb provided in the request is illegal.', Oai_Model_Error::BADVERB);
                break;
        }
    }

    /**
     * Implements response for OAI-PMH verb 'GetRecord'.
     *
     * @param  array &$oaiRequest Contains full request information
     * @return void
     */
    private function __handleGetRecord(array &$oaiRequest) {

        // Identifier references metadata Urn, not plain Id!
        // Currently implemented as 'oai:foo.bar.de:{docId}'
        $docId = substr(strrchr($oaiRequest['identifier'], ':'), 1);

        $document = null;
        try {
            $document = new Opus_Document($docId);
        } catch (Exception $ex) {
            throw new Oai_Model_Exception('The value of the identifier argument is unknown or illegal in this repository.', Oai_Model_Error::BADARGUMENT);
        }

        // do not deliver documents which are restricted by document state
        if (is_null($document) or false === in_array($document->getServerState(), $this->_deliveringDocumentStates)) {
            throw new Oai_Model_Exception('Document is not available for OAI export!', Oai_Model_Error::NORECORDSMATCH);
        }

        // for xMetaDiss it must be habilitation-thesis or doctoral-thesis
        if ('xMetaDiss' === $oaiRequest['metadataPrefix']) {
            $type = $document->getType();
            $isHabOrDoc = in_array($type, $this->_xMetaDissRestriction);
            if (false === $isHabOrDoc) {
               throw new Oai_Model_Exception("The combination of the given values results in an empty list (xMetaDiss only for habilitation and doctoralthesis).", Oai_Model_Error::NORECORDSMATCH);
            }
        }
        $this->_xml->appendChild($this->_xml->createElement('Documents'));

        $this->createXmlRecord($document);
    }

    /**
     * Implements response for OAI-PMH verb 'Identify'.
     *
     * @param  array &$oaiRequest Contains full request information
     * @return void
     */
    private function __handleIdentify(array &$oaiRequest) {

        $email = $this->_configuration->getEmailContact();
        $repName = $this->_configuration->getRepositoryName();
        $repIdentifier = $this->_configuration->getRepositoryIdentifier();
        $sampleIdentifier = $this->_configuration->getSampleIdentifier();

        // Set backup date if database query does not return a date.
        $earliestDate = new Zend_Date('1970-01-01', Zend_Date::ISO_8601);

        $earliestDateFromDb = Opus_Document::getEarliestPublicationDate();
        if (!is_null($earliestDateFromDb)) {
            $earliestDate = new Zend_Date($earliestDateFromDb, Zend_Date::ISO_8601);
        }
        $earliestDateIso = $earliestDate->get('yyyy-MM-dd');

        // set parameters for oai-pmh.xslt
        $this->_proc->setParameter('', 'emailAddress', $email);
        $this->_proc->setParameter('', 'repName', $repName);
        $this->_proc->setParameter('', 'repIdentifier', $repIdentifier);
        $this->_proc->setParameter('', 'sampleIdentifier', $sampleIdentifier);
        $this->_proc->setParameter('', 'earliestDate', $earliestDateIso);
        $this->_xml->appendChild($this->_xml->createElement('Documents'));
    }

    /**
     * Implements response for OAI-PMH verb 'ListIdentifiers'.
     *
     * @param  array &$oaiRequest Contains full request information
     * @return void
     */
    private function __handleListIdentifiers(array &$oaiRequest) {

        $max_identifier = $this->_configuration->getMaxListIdentifiers();
        $this->_handlingOfLists($oaiRequest, $max_identifier);

    }

    /**
     * Implements response for OAI-PMH verb 'ListMetadataFormats'.
     *
     * @param  array &$oaiRequest Contains full request information
     * @return void
     */
    private function __handleListMetadataFormats(array &$oaiRequest) {
        $this->_xml->appendChild($this->_xml->createElement('Documents'));

    }

    /**
     * Implements response for OAI-PMH verb 'ListRecords'.
     *
     * @param  array &$oaiRequest Contains full request information
     * @return void
     */
    private function __handleListRecords(array &$oaiRequest) {

        $max_records = $this->_configuration->getMaxListRecords();
        $this->_handlingOfLists($oaiRequest, $max_records);

    }

    /**
     * Implements response for OAI-PMH verb 'ListSets'.
     *
     * @param  array &$oaiRequest Contains full request information
     * @return void
     */
    private function __handleListSets(array &$oaiRequest) {
        $repIdentifier = $this->_configuration->getRepositoryIdentifier();

        $this->_proc->setParameter('', 'repIdentifier', $repIdentifier);
        $this->_xml->appendChild($this->_xml->createElement('Documents'));

        $sets = array();

        $finder = new Opus_DocumentFinder();
        $finder->setServerState('published');
        foreach ($finder->groupedTypesPlusCount() AS $doctype => $row) {
            $setSpec = 'doc-type:' . urlencode($doctype);
            $count = $row['count'];
            $sets[$setSpec] = "Set for document type '$doctype' ($count documents)";
        }

        $oaiRolesSets = Opus_CollectionRole::fetchAllOaiEnabledRoles();
        foreach ($oaiRolesSets AS $result) {
            if ($result['oai_name'] == 'doc-type') {
                continue;
            }

            $setSpec = urlencode($result['oai_name']);
            $count   = $result['count'];
            $sets[$setSpec] = "Set for collection '" . $result['oai_name'] . "'"
                    . " ($count documents)";

            $role = new Opus_CollectionRole($result['id']);
            foreach ($role->getOaiSetNames() AS $subset) {
                $subSetSpec  = "$setSpec:" . urlencode($subset['oai_subset']);
                $subSetCount = $subset['count'];

                $sets[$subSetSpec] = "Subset '" . $subset['oai_subset'] . "'"
                        . " for collection '" . $result['oai_name'] . "'"
                        . ': "' . trim($subset['name']) . '"'
                        . " ($subSetCount documents)";
            }
        }

        foreach ($sets as $type => $name) {
            $opus_doc = $this->_xml->createElement('Opus_Sets');
            $type_attr = $this->_xml->createAttribute('Type');
            $type_value = $this->_xml->createTextNode($type);
            $type_attr->appendChild($type_value);
            $opus_doc->appendChild($type_attr);
            $name_attr = $this->_xml->createAttribute('TypeName');
            $name_value = $this->_xml->createTextNode($name);
            $name_attr->appendChild($name_value);
            $opus_doc->appendChild($name_attr);
            $this->_xml->documentElement->appendChild($opus_doc);
        }
    }

    /**
     * Set parameters for resumptionToken-line.
     *
     * @param  string  $res value of the resumptionToken
     * @param  int     $cursor value of the cursor
     * @param  int     $totalIds value of the total Ids
     */
    private function setParamResumption($res, $cursor, $totalIds) {

       $tomorrow = str_replace('+00:00', 'Z', Zend_Date::now()->addDay(1)->setTimeZone('UTC')->getIso());
       $this->_proc->setParameter('', 'dateDelete', $tomorrow);
       $this->_proc->setParameter('', 'res', $res);
       $this->_proc->setParameter('', 'cursor', $cursor);
       $this->_proc->setParameter('', 'totalIds', $totalIds);
    }

    /**
     *
     * @param Opus_Document $document
     * @return DOMNode
     * @throws Exception
     */
    private function getDocumentXmlDomNode($document) {
        if (!in_array($document->getServerState(), $this->_deliveringDocumentStates)) {
            $message = 'Trying to get a document in server state "' . $document->getServerState() . '"';
            Zend_Registry::get('Zend_Log')->err($message);
            throw new Exception($message);
        }

        $xmlModel = new Opus_Model_Xml();
        $xmlModel->setModel($document);
        $xmlModel->excludeEmptyFields();
        $xmlModel->setStrategy(new Opus_Model_Xml_Version1);
        // $xmlModel->setXmlCache(new Opus_Model_Xml_Cache);
        return $xmlModel->getDomDocument()->getElementsByTagName('Opus_Document')->item(0);
    }

    /**
     * Create xml structure for one record
     *
     * @param  Opus_Document $document
     * @return void
     */
    private function createXmlRecord(Opus_Document $document) {
        $docId = $document->getId();
        $domNode = $this->getDocumentXmlDomNode($document);

        // add frontdoor url
        $this->_addFrontdoorUrlAttribute($domNode, $docId);

        // add ddb transfer element
        $this->_addDdbTransferElement($domNode, $docId);

        // remove file elements which should not be exported through OAI
        // Iterating over DOMNodeList is only save for readonly-operations; 
        // copy element-by-element before removing!
        $filenodes = $domNode->getElementsByTagName('File');
        $filenodes_list = array();
        foreach ($filenodes as $filenode) {
            $filenodes_list[] = $filenode;
        }

        // remove file elements which should not be exported through OAI
        foreach ($filenodes_list AS $filenode) {
            if ((false === $filenode->hasAttribute('VisibleInOai'))
                    or ('1' !== $filenode->getAttribute('VisibleInOai'))) {
                $domNode->removeChild($filenode);
            }
        }
        
        // add file download urls
        $filenodes = $domNode->getElementsByTagName('File');
        foreach ($filenodes as $filenode) {
            $this->_addFileUrlAttribute($filenode, $docId, $filenode->getAttribute('PathName'));
        }

        $node = $this->_xml->importNode($domNode, true);

        $type = $document->getType();
        $this->_addSpecInformation($node, 'doc-type:' . $type);

        $bibliography = $document->getBelongsToBibliography() == 1 ? 'true' : 'false';
        $this->_addSpecInformation($node, 'bibliography:' . $bibliography);

        $setSpecs = Oai_Model_SetSpec::getSetSpecsFromCollections($document->getCollection());
        foreach ($setSpecs AS $setSpec) {
            $this->_addSpecInformation($node, $setSpec);
        }

        $this->_xml->documentElement->appendChild($node);
    }

    /**
     * Add spec header information to DOM document.
     *
     * @param DOMNode $document
     * @param mixed   $information
     * @return void
     */
    private function _addSpecInformation(DOMNode $document, $information) {

        $set_spec_attribute = $this->_xml->createAttribute('Value');
        $set_spec_attribute_value = $this->_xml->createTextNode($information);
        $set_spec_attribute->appendChild($set_spec_attribute_value);

        $set_spec_element = $this->_xml->createElement('SetSpec');
        $set_spec_element->appendChild($set_spec_attribute);
        $document->appendChild($set_spec_element);
    }

    /**
     * Add the frontdoorurl attribute to Opus_Document XML output.
     *
     * @param DOMNode $document Opus_Document XML serialisation
     * @param string  $docid    Id of the document
     * @return void
     */
    private function _addFrontdoorUrlAttribute(DOMNode $document, $docid) {
        $url = $this->view->serverUrl() . $this->getRequest()->getBaseUrl() . '/frontdoor/index/index/docId/' . $docid;
        
        $owner = $document->ownerDocument;
        $attr = $owner->createAttribute('frontdoorurl');
        $attr->appendChild($owner->createTextNode($url));
        $document->appendChild($attr);
    }

    /**
     * Add download link url attribute to Opus_Document XML output.
     *
     * @param DOMNode $document Opus_Document XML serialisation
     * @param string  $docid    Id of the document
     * @param string  $filename File path name
     * @return void
     */  
    private function _addFileUrlAttribute(DOMNode $file, $docid, $filename) {
        $url = $this->view->serverUrl() . $this->getRequest()->getBaseUrl() . '/files/' . $docid . '/' . $filename;

        $owner = $file->ownerDocument;
        $attr = $owner->createAttribute('url');
        $attr->appendChild($owner->createTextNode($url));
        $file->appendChild($attr);
    }

    /**
     * Adds ddb contact id based on resource information.
     *
     * @param DOMNode $document
     * @param string  $docId
     * @return void
     */

    
    /**
     * Add <ddb:transfer> element for ddb container file.
     *
     * @param DOMNode $document Opus_Document XML serialisation
     * @param string  $docid    Document ID
     * @return void
     */
    private function _addDdbTransferElement(DOMNode $document, $docid) {
        $url = $this->view->serverUrl() . $this->view->baseUrl() . '/oai/container/index/docId/' . $docid;

        $fileElement = $document->ownerDocument->createElement('TransferUrl');
        $fileElement->setAttribute('PathName', $url);
        $document->appendChild($fileElement);
    }

    /**
     * Retrieve a document id by an oai identifier.
     * 
     * @param string $oaiIdentifier
     * @result int
     */
    private function getDocumentIdByOaiIdentifier($oaiIdentifier) {
        // currently oai identifers are not stored in database
        // workaround this by urn identifier
        $urnPrefix = 'urn:nbn:de';
        $localPrefix = '%'; // Workaround for different local prefixes
        $identifierInfo = mb_substr(mb_strrchr($oaiIdentifier, ':'), 1);
        $urnIdentifier = $urnPrefix . ':' . $localPrefix . ':' . $identifierInfo;

        $result = Opus_Document::getDocumentByIdentifier($urnIdentifier);
        if (null === $result) {
            $result = -1;
        }
        return $result;
    }

    /**
     * Helper method for handling lists.
     *
     * @param array &$oaiRequest
     * @param mixed $max_records
     * @return void
     */
    private function _handlingOfLists(array &$oaiRequest, $max_records) {

        if (true === empty($max_records)) {
            $max_records = 100;
        }

        $repIdentifier = $this->_configuration->getRepositoryIdentifier();
        $tempPath = $this->_configuration->getResumptionTokenPath();

        $this->_proc->setParameter('', 'repIdentifier', $repIdentifier);
        $this->_xml->appendChild($this->_xml->createElement('Documents'));
        // do some initialisation
        $cursor = 0;
        $totalIds = 0;
        $res = '';
        $resParam = '';
        $start = $max_records + 1;
        $restIds = array();
        $reldocIds = array();

        $metadataPrefix = null;
        if (true === array_key_exists('metadataPrefix', $oaiRequest)) {
            $metadataPrefix = $oaiRequest['metadataPrefix'];
        }

        $token = new Oai_Model_Resumptiontoken;

        $tokenWorker = new Oai_Model_Resumptiontokens;
        $tokenWorker->setResumptionPath($tempPath);

        // parameter resumptionToken is given
        if (false === empty($oaiRequest['resumptionToken'])) {

            $resParam = $oaiRequest['resumptionToken'];
            $token = $tokenWorker->getResumptionToken($resParam);

            if (true === is_null($token)) {
                throw new Oai_Model_Exception("file could not be read.", Oai_Model_Error::BADRESUMPTIONTOKEN);
            }

            $cursor = $token->getStartPosition() - 1;
            $start = $token->getStartPosition() + $max_records;
            $totalIds = $token->getTotalIds();
            $reldocIds = $token->getDocumentIds();
            $metadataPrefix = $token->getMetadataPrefix();
            $this->_proc->setParameter('', 'oai_metadataPrefix', $metadataPrefix);

        // no resumptionToken is given
        } else {
            $reldocIds = $this->getDocumentIdsByOaiRequest($oaiRequest);
        }

        // handling of document ids
        $restIds = $reldocIds;
        $workIds = array_splice($restIds, 0, $max_records);
        foreach ($workIds as $docId) {
            $document = new Opus_Document($docId);
            $this->createXmlRecord($document);
        }

        // no records returned
        if (true === empty($workIds)) {
            throw new Oai_Model_Exception("The combination of the given values results in an empty list.", Oai_Model_Error::NORECORDSMATCH);
        }

        // store the further Ids in a resumption-file
        $countRestIds = count($restIds);
        if ($countRestIds > 0) {
            if (0 === $totalIds) {
                $totalIds = $max_records + $countRestIds;
            }

            $token->setStartPosition($start);
            $token->setTotalIds($totalIds);
            $token->setDocumentIds($restIds);
            $token->setMetadataPrefix($metadataPrefix);

            $tokenWorker->storeResumptionToken($token);

            $res = $token->getResumptionId();
        }

        // set parameters for the resumptionToken-node
        if ((false === empty($resParam)) || ($countRestIds > 0)) {
            $this->setParamResumption($res, $cursor, $totalIds);
        }
    }

    /**
     * Retrieve all document ids for a valid oai request.
     *
     * @param array &$oaiRequest
     * @return array
     */
    private function getDocumentIdsByOaiRequest(array &$oaiRequest) {
        $finder = new Opus_DocumentFinder();

        // add server state restrictions
        $finder->setServerStateInList($this->_deliveringDocumentStates);

        $metadataPrefix = $oaiRequest['metadataPrefix'];
        if ('xMetaDiss' === $metadataPrefix) {
            $finder->setTypeInList($this->_xMetaDissRestriction);
        }
        if ('epicur' === $metadataPrefix) {
            $finder->setIdentifierTypeExists('urn');
        }

        if (array_key_exists('set', $oaiRequest)) {
            $setarray = explode(':', $oaiRequest['set']);
            if (!empty($setarray[1])) {
                $finder->setType($setarray[1]);
            }
        }

        if (array_key_exists('from', $oaiRequest) and !empty($oaiRequest['from'])) {
            $from = DateTime::createFromFormat('Y-m-d', $oaiRequest['from']);
            $finder->setServerDateModifiedAfter($from->format('Y-m-d'));
        }

        if (array_key_exists('until', $oaiRequest)) {
            $until = DateTime::createFromFormat('Y-m-d', $oaiRequest['until']);
            $until->add(new DateInterval('P1D'));
            $finder->setServerDateModifiedBefore($until->format('Y-m-d'));
        }

        return $finder->ids();
    }
}
