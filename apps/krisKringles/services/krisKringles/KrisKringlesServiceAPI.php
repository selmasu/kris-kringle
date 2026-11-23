<?php
class KrisKringleServiceAPI extends ServiceAPI
{
	//Function Names
    const GET_KRIS_KRINGLES             = 'getKrisKringles';
    const CREATE_KRIS_KRINGLE           = 'createKrisKringle';
    const UPDATE_PICTURE = 'updateKrisKringlePicture';
    const GET_PICTURE = 'getKrisKringlePicture';
	
	//Argument Names
	const DATA                          = 'data';
    const ID                            = 'id';
	
	/**
	 * @var KrisKringleService
	 */
	private $krisKringleService;
	
	public function __construct( Selmasu $s )
	{
		parent::__construct( $s );
		
		$this->krisKringleService = KrisKringleService::create( $s );
	}

    /**
     * @param string $functionToCall
     * @param array $arguments
     * @return array
     */
	public function api( $functionToCall, $arguments = array() )
	{
		$this->arguments( $arguments );
		
		switch( $functionToCall ) {
            case self::GET_KRIS_KRINGLES:
                return $this->krisKringleService->getKrisKringles(ListArguments::create()->fromArray($arguments));

            case self::CREATE_KRIS_KRINGLE:
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    return ['error' => 'Creating a Kris Kringle requires a POST request.'];
                }
                $name = $arguments['name'] ?? null;
                $firstGroupName = $arguments['firstGroupName'] ?? null;
                $copyFromId = filter_var($arguments['copyFromId'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($copyFromId === false) {
                    return ['error' => 'Choose a valid previous Kris Kringle.'];
                }
                return $this->krisKringleService->createKrisKringle(
                    is_string($name) ? $name : '', is_string($firstGroupName) ? $firstGroupName : '', $_FILES['picture'] ?? null, $copyFromId
                );

            case 'getKrisKringle':
                header('Cache-Control: private, no-store');
                return $this->krisKringleService->getKrisKringle($this->intArgument(self::ID));

            case 'updateKrisKringle':
            case 'addKrisKringleGroup':
            case 'addKrisKringleMember':
            case 'updateKrisKringleMember':
            case 'addKrisKringleChild':
            case 'getGiftRecipientOptions':
            case 'updateGiftAssignment':
            case 'setGiftRecipients':
            case 'clearGiftRecipients':
                $token = $_POST['selmasuToken'] ?? null;
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($token) || $token === ''
                    || !hash_equals((string)$this->s()->user()->selmasuToken()->get(), $token)) {
                    return ['error' => 'Please sign in again.'];
                }
                $id = $this->intArgument(self::ID);
                if ($functionToCall === 'setGiftRecipients' || $functionToCall === 'clearGiftRecipients') {
                    header('Cache-Control: private, no-store');
                    return $this->krisKringleService->updateAllGiftRecipients($id, $functionToCall === 'clearGiftRecipients');
                }
                if ($functionToCall === 'getGiftRecipientOptions' || $functionToCall === 'updateGiftAssignment') {
                    header('Cache-Control: private, no-store');
                    $idOptions = ['options' => ['min_range' => 0]];
                    $giverId = filter_var($_POST['giverId'] ?? 0, FILTER_VALIDATE_INT, $idOptions);
                    $giverUserId = filter_var($_POST['giverUserId'] ?? 0, FILTER_VALIDATE_INT, $idOptions);
                    if ($giverId === false || $giverUserId === false || (bool)$giverId === (bool)$giverUserId) {
                        return ['error' => 'Choose a member of this Kris Kringle.'];
                    }
                    if ($functionToCall === 'getGiftRecipientOptions') {
                        return $this->krisKringleService->getGiftRecipientOptions($id, $giverId, $giverUserId);
                    }
                    $recipientId = filter_var($_POST['recipientId'] ?? null, FILTER_VALIDATE_INT, $idOptions);
                    $expected = filter_var($_POST['expectedRecipientId'] ?? null, FILTER_VALIDATE_INT, $idOptions);
                    if ($recipientId === false || $expected === false) {
                        return ['error' => 'Choose a valid gift recipient.'];
                    }
                    return $this->krisKringleService->updateGiftAssignment($id, $giverId, $giverUserId, $recipientId, $expected);
                }
                if ($functionToCall === 'updateKrisKringleMember') {
                    header('Cache-Control: private, no-store');
                    $options = ['options' => ['min_range' => 0]];
                    $memberId = filter_var($_POST['memberId'] ?? 0, FILTER_VALIDATE_INT, $options);
                    $userId = filter_var($_POST['userId'] ?? 0, FILTER_VALIDATE_INT, $options);
                    $expectedUserId = filter_var($_POST['expectedUserId'] ?? null, FILTER_VALIDATE_INT, $options);
                    $name = $_POST['firstName'] ?? null;
                    $email = $_POST['email'] ?? null;
                    if ($memberId === false || $userId === false || $expectedUserId === false
                        || (bool)$memberId === (bool)$userId || !is_string($name) || !is_string($email)) {
                        return ['error' => 'Enter valid member details.'];
                    }
                    return $this->krisKringleService->updateKrisKringleMember(
                        $id, $memberId, $userId, $expectedUserId, $name, $email
                    );
                }
                if ($functionToCall === 'addKrisKringleGroup') {
                    header('Cache-Control: private, no-store');
                    $name = $_POST['name'] ?? null;
                    return $this->krisKringleService->addKrisKringleGroup($id, is_string($name) ? $name : '');
                }
                if ($functionToCall === 'addKrisKringleMember') {
                    header('Cache-Control: private, no-store');
                    $email = $_POST['email'] ?? null;
                    $firstName = $_POST['firstName'] ?? '';
                    if (!is_string($firstName)) {
                        return ['error' => 'Enter a valid first name.'];
                    }
                    $groupId = filter_var($_POST['groupId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    return $this->krisKringleService->addKrisKringleMember(
                        $id, $groupId ?: 0, is_string($email) ? $email : '', $firstName
                    );
                }
                if ($functionToCall === 'addKrisKringleChild') {
                    header('Cache-Control: private, no-store');
                    $name = $_POST['name'] ?? null;
                    $parentId = filter_var($_POST['parentUserId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    return $this->krisKringleService->addKrisKringleChild(
                        $id, $parentId ?: 0, is_string($name) ? $name : ''
                    );
                }
                $name = $_POST['name'] ?? null;
                if ($name !== null && !is_string($name)) {
                    return ['error' => 'Enter a valid name.'];
                }
                return $this->krisKringleService->updateKrisKringle($id, $name, $_FILES['picture'] ?? null);

            case self::UPDATE_PICTURE:
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    return ['error' => 'Uploading a group photo requires a POST request.'];
                }
                return $this->krisKringleService->updateKrisKringlePicture($this->intArgument(self::ID), $_FILES['picture'] ?? null);

            case 'getKrisKringleMemberPicture':
            case self::GET_PICTURE:
                $id = $this->intArgument(self::ID);
                $picture = $functionToCall === 'getKrisKringleMemberPicture'
                    ? $this->krisKringleService->getKrisKringleMemberPicture($id, $this->intArgument('userId'))
                    : $this->krisKringleService->getKrisKringlePicture($id);
                if ($picture === null) {
                    http_response_code(404);
                    return ['error' => 'Photo not found.'];
                }
                header('Content-Type: image/jpeg');
                header('Content-Length: ' . strlen($picture));
                header('Cache-Control: private, no-store');
                header('X-Content-Type-Options: nosniff');
                echo $picture;
                exit;
		}
	}

	/**
	 * Static creator
	 * @param Selmasu $s
	 * @return KrisKringleServiceAPI
	 */
	public static function create( Selmasu $s ):KrisKringleServiceAPI{
		return new self( $s );
	}
}