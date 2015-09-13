<?php
/**
 * ownCloud / SabreDAV
 *
 * @author Markus Goetz
 *
 * @copyright Copyright (C) 2007-2013 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */

/**
 * Class OC_Connector_Sabre_Server
 *
 * This class reimplements some methods from @see \Sabre\DAV\Server.
 *
 * Basically we add handling of depth: infinity.
 *
 * The right way to handle this would have been to submit a patch to the upstream project
 * and grab the corresponding version one merged.
 *
 * Due to time constrains and the limitations where we don't want to upgrade 3rdparty code in
 * this stage of the release cycle we did choose this approach.
 *
 * For ownCloud 7 we will upgrade SabreDAV and submit the patch - if needed.
 *
 * @see \Sabre\DAV\Server
 *
 * This class is a modified version of OC_Connector_Sabre_Server, described above.
 * It allows hiding folders from sync clients and does not set the mime type of html files to
 * text/plain (potential security risk).
 *
 */
class OC_Connector_Sabre_Server_chooser extends Sabre\DAV\Server {

	/**
	 * @see \Sabre\DAV\Server
	 */
	protected function httpPropfind($uri) {

		// $xml = new \Sabre\DAV\XMLReader(file_get_contents('php://input'));
		$requestedProperties = $this->parsePropFindRequest($this->httpRequest->getBody(true));

		$depth = $this->getHTTPDepth(1);
		// The only two options for the depth of a propfind is 0 or 1
		// if ($depth!=0) $depth = 1;
		
		if(array_key_exists('REDIRECT_URL', $_SERVER) && (strpos($_SERVER['REDIRECT_URL'], "/remote.php/webdav/")===0 || $_SERVER['REDIRECT_URL']==="/remote.php/webdav")){
			$this->setBaseUri("/remote.php/webdav/");
		}
		
		//OC_Log::write('chooser','uri: '.$uri, OC_Log::WARN);

		$newProperties = $this->getPropertiesForPath($uri,$requestedProperties,$depth);

		// This is a multi-status response
		$this->httpResponse->sendStatus(207);
		$this->httpResponse->setHeader('Content-Type','application/xml; charset=utf-8');
		$this->httpResponse->setHeader('Vary','Brief,Prefer');

		// Normally this header is only needed for OPTIONS responses, however..
		// iCal seems to also depend on these being set for PROPFIND. Since
		// this is not harmful, we'll add it.
		$features = array('1','3', 'extended-mkcol');
		foreach($this->plugins as $plugin) {
			$features = array_merge($features,$plugin->getFeatures());
		}

		$this->httpResponse->setHeader('DAV',implode(', ',$features));

		$prefer = $this->getHTTPPrefer();
		$minimal = $prefer['return-minimal'];

		$data = $this->generateMultiStatus($newProperties, $minimal);
		$this->httpResponse->sendBody($data);

	}

	/**
	 * Small helper to support PROPFIND with DEPTH_INFINITY.
	 */
	private function addPathNodesRecursively(&$nodes, $path) {
		foreach($this->tree->getChildren($path) as $childNode) {
                        if($this->excludePath($path) || $this->excludePath($path . '/' . $childNode->getName())){
                            continue;
                        }
			$nodes[$path . '/' . $childNode->getName()] = $childNode;
			if ($childNode instanceof \Sabre\DAV\ICollection)
				$this->addPathNodesRecursively($nodes, $path . '/' . $childNode->getName());
		}
	}
	
	/**
	* Small helper to reverse getSecureMimeType for html files - i.e. set
	* Content-type to text/html instead of the more secure text/plain.
	*/
	private static function unsecureContentType($filepath, $contentType){
		if(substr($filepath, -5)===".html"){
			$contentType = "text/html";
		}
		return $contentType;
	}
	
	// TODO: get to work with sharding.
	private function resolveSharedPath($path){
		//$absPath = $this->tree->getFileView()->getAbsolutePath($path);
		//$realPath = \OC\Files\Filesystem::resolvePath('/' . $absPath);
		//if(isset($realPath[0]) && get_class($realPath[0])=='OC\Files\Storage\Shared'){
		//OC_Log::write('chooser','path: '.$path, OC_Log::WARN);
		if(preg_match('/^\/*Shared\//',$path)){
			$sharePath = preg_replace('/^\/*Shared\//', '', $path);
			if(\OCP\App::isEnabled('files_sharding')){
				$sourcePath = \OC_Shard_Backend_File::getSource($sharePath);
			}
			else{
				$sourcePath = \OC_Share_Backend_File::getSource($sharePath);
			}
			$path = preg_replace('/^\/*files\//', '/', $sourcePath['path']);
			//OC_Log::write('chooser','path: '.$sharePath.':'.$path, OC_Log::WARN);
		}
		return $path;
	}

	/**
	* Small helper to hide folders from sync clients.
	*/
	private function excludePath($path){
		$sourcePath = $this->resolveSharedPath($path);
		//if(stripos($_SERVER['HTTP_USER_AGENT'], "cadaver")===false && stripos($_SERVER['HTTP_USER_AGENT'], "curl")===false){
		if(stripos($_SERVER['HTTP_USER_AGENT'], "mirall")===false && stripos($_SERVER['HTTP_USER_AGENT'], "csyncoC")===false){
			return false;
		}
		if(!\OCP\App::isEnabled('files_sharding')){
			return false;
		}
		return \OCA\FilesSharding\Lib::inDataFolder($path);
	}

	public function getPropertiesForPath($path, $propertyNames = array(), $depth = 0) {
		
		//	if ($depth!=0) $depth = 1;
		
		$path = rtrim($path,'/');
		
		$returnPropertyList = array();
		
		// Hide the folder completely
		if($this->excludePath($path)){
			return $returnPropertyList;
		}
		
		$parentNode = $this->tree->getNodeForPath($path);
		$nodes = array(
			$path => $parentNode
		);
		if ($depth==1 && $parentNode instanceof \Sabre\DAV\ICollection) {
			$children = $this->tree->getChildren($path);
			foreach($children as $childNode){
				//OC_Log::write('chooser','node: '.$path.":".$depth.":".$childNode->getName(), OC_Log::WARN);
				if($this->excludePath($path . '/' . $childNode->getName())){
					continue;
				}
				$nodes[$path . '/' . $childNode->getName()] = $childNode;
			}
		} else if ($depth == self::DEPTH_INFINITY && $parentNode instanceof \Sabre\DAV\ICollection) {
			$this->addPathNodesRecursively($nodes, $path);
		}
		
		//OC_Log::write('chooser','nodes: '.$path.":".$depth.":".count($nodes), OC_Log::WARN);


		// If the propertyNames array is empty, it means all properties are requested.
		// We shouldn't actually return everything we know though, and only return a
		// sensible list.
		$allProperties = count($propertyNames)==0;

		foreach($nodes as $myPath=>$node) {

			$currentPropertyNames = $propertyNames;

			$newProperties = array(
				'200' => array(),
				'404' => array(),
			);

			if ($allProperties) {
				// Default list of propertyNames, when all properties were requested.
				$currentPropertyNames = array(
					'{DAV:}getlastmodified',
					'{DAV:}getcontentlength',
					'{DAV:}resourcetype',
					'{DAV:}quota-used-bytes',
					'{DAV:}quota-available-bytes',
					'{DAV:}getetag',
					'{DAV:}getcontenttype',
				);
			}

			// If the resourceType was not part of the list, we manually add it
			// and mark it for removal. We need to know the resourcetype in order
			// to make certain decisions about the entry.
			// WebDAV dictates we should add a / and the end of href's for collections
			$removeRT = false;
			if (!in_array('{DAV:}resourcetype',$currentPropertyNames)) {
				$currentPropertyNames[] = '{DAV:}resourcetype';
				$removeRT = true;
			}

			$result = $this->broadcastEvent('beforeGetProperties',array($myPath, $node, &$currentPropertyNames, &$newProperties));
			// If this method explicitly returned false, we must ignore this
			// node as it is inaccessible.
			if ($result===false) continue;

			if (count($currentPropertyNames) > 0) {

				if ($node instanceof \Sabre\DAV\IProperties) {
					$nodeProperties = $node->getProperties($currentPropertyNames);

					// The getProperties method may give us too much,
					// properties, in case the implementor was lazy.
					//
					// So as we loop through this list, we will only take the
					// properties that were actually requested and discard the
					// rest.
					foreach($currentPropertyNames as $k=>$currentPropertyName) {
						if (isset($nodeProperties[$currentPropertyName])) {
							unset($currentPropertyNames[$k]);
							$newProperties[200][$currentPropertyName] = $nodeProperties[$currentPropertyName];
						}
					}

				}

			}

			foreach($currentPropertyNames as $prop) {

				if (isset($newProperties[200][$prop])) continue;

				switch($prop) {
					case '{DAV:}getlastmodified'       : if ($node->getLastModified()) $newProperties[200][$prop] = new \Sabre\DAV\Property\GetLastModified($node->getLastModified()); break;
					case '{DAV:}getcontentlength'      :
						if ($node instanceof \Sabre\DAV\IFile) {
							$size = $node->getSize();
							if (!is_null($size)) {
								$newProperties[200][$prop] = (int)$node->getSize();
							}
						}
						break;
					case '{DAV:}quota-used-bytes'      :
						if ($node instanceof \Sabre\DAV\IQuota) {
							$quotaInfo = $node->getQuotaInfo();
							$newProperties[200][$prop] = $quotaInfo[0];
						}
						break;
					case '{DAV:}quota-available-bytes' :
						if ($node instanceof \Sabre\DAV\IQuota) {
							$quotaInfo = $node->getQuotaInfo();
							$newProperties[200][$prop] = $quotaInfo[1];
						}
						break;
					case '{DAV:}getetag'               : if ($node instanceof \Sabre\DAV\IFile && $etag = $node->getETag())  $newProperties[200][$prop] = $etag; break;
					case '{DAV:}getcontenttype'        : if ($node instanceof \Sabre\DAV\IFile && $ct = self::unsecureContentType($path, $node->getContentType()))  $newProperties[200][$prop] = $ct; break;
					case '{DAV:}supported-report-set'  :
						$reports = array();
						foreach($this->plugins as $plugin) {
							$reports = array_merge($reports, $plugin->getSupportedReportSet($myPath));
						}
						$newProperties[200][$prop] = new \Sabre\DAV\Property\SupportedReportSet($reports);
						break;
					case '{DAV:}resourcetype' :
						$newProperties[200]['{DAV:}resourcetype'] = new \Sabre\DAV\Property\ResourceType();
						foreach($this->resourceTypeMapping as $className => $resourceType) {
							if ($node instanceof $className) $newProperties[200]['{DAV:}resourcetype']->add($resourceType);
						}
						break;

				}

				// If we were unable to find the property, we will list it as 404.
				if (!$allProperties && !isset($newProperties[200][$prop])) $newProperties[404][$prop] = null;

			}

			$this->broadcastEvent('afterGetProperties',array(trim($myPath,'/'),&$newProperties, $node));

			$newProperties['href'] = trim($myPath,'/');

			// Its is a WebDAV recommendation to add a trailing slash to collectionnames.
			// Apple's iCal also requires a trailing slash for principals (rfc 3744), though this is non-standard.
			if ($myPath!='' && isset($newProperties[200]['{DAV:}resourcetype'])) {
				$rt = $newProperties[200]['{DAV:}resourcetype'];
				if ($rt->is('{DAV:}collection') || $rt->is('{DAV:}principal')) {
					$newProperties['href'] .='/';
				}
			}

			// If the resourcetype property was manually added to the requested property list,
			// we will remove it again.
			if ($removeRT) unset($newProperties[200]['{DAV:}resourcetype']);

			$returnPropertyList[] = $newProperties;

		}

		//OC_Log::write('chooser','Properties: '.serialize($returnPropertyList), OC_Log::WARN);
		
		return $returnPropertyList;

	}
	
	public function broadcastEvent($eventName, $arguments = array()) {
		if(isset($this->eventSubscriptions[$eventName])) {
			foreach($this->eventSubscriptions[$eventName] as $subscriber) {
				if($eventName=='afterWriteContent' || $eventName=='afterCreateFile'){
					$this->tree->fixPath($arguments[0]);
					OC_Log::write('chooser', 'Fixed path: '.$eventName." -> ".$arguments[0], OC_Log::DEBUG);
				}
				$result = call_user_func_array($subscriber, $arguments);
				if ($result===false) return false;
			}
		}
		return true;
  }
	
}
