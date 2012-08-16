<?php
/**
 * All task related to json file.
 * 
 * Usage:
 * <code>
 * $json = new JsonReader();
 * $json->cloneJson($url);
 * $json->saveJson($url,$latestReleaseData);
 * </code>
 *
 * @package extensionmanager
 */
use Composer\Config;
use Composer\IO\NullIO;
use Composer\Factory;
use Composer\Repository\VcsRepository;
use Composer\Repository\RepositoryManager;
use Composer\package\Dumper\ArrayDumper;
use Composer\Json\JsonFile;

class JsonHandler extends Controller {
	
	public $url;
	public $latestReleaseData;
	public $versionData;
	public $availableVersions;
	public $repo;
	public $errorInConstructer;
	public $packageName;

	public function __construct($url) {
		$this->url = $url;
		$config = Factory::createConfig();
		$this->repo = new VcsRepository(array('url' => $url,''), new NullIO(), $config);
		
	}

	/**
	  * Convert a module url into json content 
	  *
	  * @param string $url
	  * @return array $data
	  */
	public function cloneJson() { 
		$jsonData = array();
		try{

			$versions =  $this->repo->getPackages();
			
			if($versions) {
				$releaseDateTimeStamps = array();
				$this->versionData = $versions;
				$this->availableVersions = count($this->versionData);

				for ($i=0; $i < $this->availableVersions ; $i++) {
					array_push($releaseDateTimeStamps, date_timestamp_get($this->versionData[$i]->getReleaseDate()));
				}

				foreach ($releaseDateTimeStamps as $key => $val) {
					if ($val == max($releaseDateTimeStamps)) {
						$this->latestReleaseData = $this->versionData[$key];
					}
				}
			}

			$this->packageName = $this->latestReleaseData->getPrettyName();

			if (!isset($this->packageName)){
				throw new InvalidArgumentException("The package name was not found in the composer.json at '"
					.$this->url."' ");
			}

			if (!preg_match('{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9]([_.-]?[a-z0-9]+)*$}i', $this->packageName)) {
				throw new InvalidArgumentException(
					"The package name '{$this->packageName}' is invalid, it should have a vendor name,
					a forward slash, and a package name. The vendor and package name can be words separated by -, . or _.
					The complete name should match '[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9]([_.-]?[a-z0-9]+)*' at "
					.$this->url."'");
			}

			if (preg_match('{[A-Z]}', $this->packageName)) {
				$suggestName = preg_replace('{(?:([a-z])([A-Z])|([A-Z])([A-Z][a-z]))}', '\\1\\3-\\2\\4', $this->packageName);
				$suggestName = strtolower($suggestName);

				throw new InvalidArgumentException(
					"The package name '{$this->packageName}' is invalid,
					it should not contain uppercase characters. We suggest using '{$suggestName}' instead. at '"
					.$this->url."' ");
			}
		} catch (Exception $e) {
			$jsonData['ErrorMsg'] = $e->getMessage();
			return $jsonData;
		}

		$jsonData['AllRelease'] = $this->versionData;
		$jsonData['LatestRelease'] = $this->latestReleaseData;
		return $jsonData;		
	}

	/**
	  * Save json content in database  
	  *
	  * @return boolean
	  */
	function saveJson() {
		$ExtensionData = new ExtensionData();
		$ExtensionData->SubmittedByID = Member::currentUserID();
		$ExtensionData->Url = $this->url;
		$result = $this->dataFields($ExtensionData);
		return $result ;
	}			

	/**
	  * update json content in database  
	  *
	  * @return boolean
	  */
	function updateJson() {

		$ExtensionData = ExtensionData::get()->filter(array("Url" => $this->url))->First();

		if($ExtensionData) {
			$result = $this->dataFields($ExtensionData);
			return $result ;
		} else {
			return Null ;
		}		
	}

	/**
	  * Save each property of json content 
	  * in corresponidng field of database  
	  *
	  * @param  object $ExtensionData 
	  * @return boolean
	  */
	function dataFields($ExtensionData) {
		$saveDataFields = array();
		try{
			if($this->latestReleaseData->getPrettyName()) {
				list($vendorName, $moduleName) = explode("/", $this->latestReleaseData->getPrettyName());
				$ExtensionData->Name = $moduleName;
			} else {
				throw new InvalidArgumentException("We could not find Name field in composer.json at'"
					.$this->url."' ");
			}

			if($this->latestReleaseData->getDescription()) {
				$ExtensionData->Description = $this->latestReleaseData->getDescription();
			} else {
				throw new InvalidArgumentException("We could not find Description field in composer.json at'"
					.$this->url."' ");
			}

			if($this->latestReleaseData->getPrettyVersion()) {
				$ExtensionData->Version = $this->latestReleaseData->getPrettyVersion();
			}

			if($this->latestReleaseData->getType()) {
				$type = $this->latestReleaseData->getType() ;
				if(preg_match("/\bmodule\b/i", $type)){

					$ExtensionData->Type = 'Module';

				} elseif(preg_match("/\btheme\b/i", $type)) {

					$ExtensionData->Type = 'Theme';

				} elseif(preg_match("/\bwidget\b/i", $type)) {

					$ExtensionData->Type = 'Widget';
				} else {
					throw new InvalidArgumentException("We could not find 'Type' field in composer.json at'"
						.$this->url."' ");
				}
			}

			if($this->latestReleaseData->getHomepage()) {
				$ExtensionData->Homepage = $this->latestReleaseData->getHomepage();
			}

			if($this->latestReleaseData->getReleaseDate()) {
				$ExtensionData->ReleaseTime = $this->latestReleaseData->getReleaseDate()->format('Y-m-d H:i:s');
			}

			if($this->latestReleaseData->getLicense()) {
				$ExtensionData->Licence = $this->latestReleaseData->getLicense();
			}

			if($this->latestReleaseData->getSupport()) {
				$supportData = $this->latestReleaseData->getSupport() ;
				if(array_key_exists('email',$supportData)) {
					$ExtensionData->SupportEmail = $supportData['email'];
				}
				if(array_key_exists('issues',$supportData)) {
					$ExtensionData->SupportIssues = $supportData['issues'];
				}
				if(array_key_exists('forum',$supportData)) {
					$ExtensionData->SupportForum = $supportData['forum'];
				}
				if(array_key_exists('wiki',$supportData)) {
					$ExtensionData->SupportWiki = $supportData['wiki'];
				}
				if(array_key_exists('irc',$supportData)) {
					$ExtensionData->SupportIrc = $supportData['irc'];
				}
				if(array_key_exists('source',$supportData)) {
					$ExtensionData->SupportSource = $supportData['source'];
				}
			}

			if($this->latestReleaseData->getTargetDir()) {
				$ExtensionData->TargetDir = $this->latestReleaseData->getTargetDir();
			}

			if($this->latestReleaseData->getRequires()) {
				$ExtensionData->Require = serialize($this->latestReleaseData->getRequires());
			} else {
				throw new InvalidArgumentException("We could not find Require field in composer.json at'"
					.$this->url."' ");
			}

			if($this->latestReleaseData->getDevRequires()) {
				$ExtensionData->RequireDev = serialize($this->latestReleaseData->getDevRequires());
			}

			if($this->latestReleaseData->getConflicts()) {
				$ExtensionData->Conflict = serialize($this->latestReleaseData->getConflicts());
			}

			if($this->latestReleaseData->getReplaces()) {
				$ExtensionData->Replace = serialize($this->latestReleaseData->getReplaces());
			}

			if($this->latestReleaseData->getProvides()) {
				$ExtensionData->Provide = serialize($this->latestReleaseData->getProvides());
			}

			if($this->latestReleaseData->getSuggests()) {
				$ExtensionData->Suggest = serialize($this->latestReleaseData->getSuggests());
			}

			if($this->latestReleaseData->getExtra()) {
				$ExtensionData->Extra = serialize($this->latestReleaseData->getExtra());
				$extra = $this->latestReleaseData->getExtra();
				if(array_key_exists('snapshot',$extra)) {
					$ExtensionData->ThumbnailID = ExtensionSnapshot::saveSnapshot($extra['snapshot'],$this->latestReleaseData->getPrettyName());
				} else {
					throw new InvalidArgumentException("We could not find SnapShot url field in composer.json at'"
						.$this->url."' ");
				}
			}

			if($this->latestReleaseData->getRepositories()) {
				$ExtensionData->Repositories = serialize($this->latestReleaseData->getRepositories());
			}

			if($this->latestReleaseData->getIncludePaths()) {
				$ExtensionData->IncludePath = serialize($this->latestReleaseData->getIncludePaths());
			}

			if($this->latestReleaseData->getMinimumStability()) {
				$ExtensionData->MinimumStability = $this->latestReleaseData->getMinimumStability();
			}

			if($this->latestReleaseData->getAuthors()) {
				ExtensionAuthorController::storeAuthorsInfo($this->latestReleaseData->getAuthors(),$ExtensionData->ID);
			} else {
				throw new InvalidArgumentException("We could not find Author Info field in composer.json at'"
					.$this->url."' ");
			}

			if($this->latestReleaseData->getKeywords()) {
				ExtensionKeywords::saveKeywords($this->latestReleaseData->getKeywords(),$ExtensionData->ID);
			} else {
				throw new InvalidArgumentException("We could not find Keywords field in composer.json at'"
					.$this->url."' ");
			}

		} catch(Exception $e){
			$saveDataFields['ErrorMsg'] = $e->getMessage();
			return $saveDataFields;
		}

		$ExtensionData->write();
		$saveDataFields['ExtensionID'] = $ExtensionData->ID;
		return $saveDataFields;
	}

	/**
	  * Save Version related data of Extension 
	  *
	  * @param int $id  
	  * @return boolean
	  */
	public function saveVersionData($id) {
		
		
		for ($i=0; $i < $this->availableVersions ; $i++) { 
			$version = new ExtensionVersion();
			$version->ExtensionDataID = $id;
			$result = $this->versionDataField($version,$this->versionData[$i]);
		}
		return $result ;
	}

	/**
	  * Delete old version of extension  
	  *
	  * @param  int $id 
	  * @return boolean
	  */
	public function deleteVersionData($id){
		return ExtensionVersion::get()->filter('ExtensionDataID', $id)->removeAll();
	}

	/**
	  * Save each version related property of json content 
	  *
	  * @param  object $version, object $Data 
	  * @return boolean
	  */
	public function versionDataField($version,$data) {
		
		if($data->getSourceType()) {
			$version->SourceType = $data->getSourceType();
		}

		if($data->getSourceUrl()) {
			$version->SourceUrl = $data->getSourceUrl();
		}
		
		if($data->getSourceReference()) {
			$version->SourceReference = $data->getSourceReference();
		}

		if($data->getDistType()) {
			$version->DistType = $data->getDistType();
		}

		if($data->getDistUrl()) {
			$version->DistUrl = $data->getDistUrl();
		}

		if($data->getDistReference()) {
			$version->DistReference = $data->getDistReference();
		}

		if($data->getDistSha1Checksum()) {
			$version->DistSha1Checksum = $data->getDistSha1Checksum();
		}

		if($data->getVersion()) {
			$version->Version = $data->getVersion();
		}

		if($data->getPrettyVersion()) {
			$version->PrettyVersion = $data->getPrettyVersion();
		}

		if($data->getReleaseDate()) {
			$version->ReleaseDate = $data->getReleaseDate()->format('Y-m-d H:i:s');
		}
		
		$version->write();
		return true;
	}
}