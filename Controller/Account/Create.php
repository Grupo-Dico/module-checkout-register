<?php

declare(strict_types=1);

namespace GDMexico\CheckoutRegister\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Registration;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw as RawResult;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates a customer account via AJAX from the checkout authentication modal.
 *
 * Expects an XHR POST with a JSON body:
 * {"firstname": "", "lastname": "", "email": "", "password": "", "password_confirmation": ""}
 *
 * On success the customer is logged in. The guest quote is merged into the customer
 * quote by the standard customer_login observers, so the cart is preserved.
 */
class Create implements HttpPostActionInterface
{
    private const BAD_REQUEST = 400;

    private $request;
    private $resultJsonFactory;
    private $resultRawFactory;
    private $serializer;
    private $customerFactory;
    private $accountManagement;
    private $groupManagement;
    private $customerSession;
    private $registration;
    private $storeManager;
    private $cookieManager;
    private $cookieMetadataFactory;
    private $logger;

    public function __construct(
        HttpRequest $request,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        JsonSerializer $serializer,
        CustomerInterfaceFactory $customerFactory,
        AccountManagementInterface $accountManagement,
        GroupManagementInterface $groupManagement,
        CustomerSession $customerSession,
        Registration $registration,
        StoreManagerInterface $storeManager,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->serializer = $serializer;
        $this->customerFactory = $customerFactory;
        $this->accountManagement = $accountManagement;
        $this->groupManagement = $groupManagement;
        $this->customerSession = $customerSession;
        $this->registration = $registration;
        $this->storeManager = $storeManager;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->logger = $logger;
    }

    /**
     * @return JsonResult|RawResult
     */
    public function execute()
    {
        $data = $this->getPayload();
        if ($data === null) {
            return $this->resultRawFactory->create()->setHttpResponseCode(self::BAD_REQUEST);
        }

        if ($this->customerSession->isLoggedIn()) {
            return $this->respond(false, __('You are already signed in.'));
        }

        if (!$this->registration->isAllowed()) {
            return $this->respond(true, __('Account registration is currently disabled.'));
        }

        $password = (string)($data['password'] ?? '');
        if ($password !== (string)($data['password_confirmation'] ?? '')) {
            return $this->respond(true, __('Please make sure your passwords match.'));
        }

        try {
            $customer = $this->accountManagement->createAccount($this->buildCustomer($data), $password);

            $confirmation = $this->accountManagement->getConfirmationStatus((int)$customer->getId());
            if ($confirmation === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                return $this->respond(
                    false,
                    __('You must confirm your account. Please check your email for the confirmation link.'),
                    ['requiresConfirmation' => true]
                );
            }

            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->deleteCacheSessionCookie();

            return $this->respond(false, __('Your account has been created.'));
        } catch (InputException $e) {
            $messages = [];
            foreach ($e->getErrors() as $error) {
                $messages[] = $error->getMessage();
            }
            return $this->respond(true, $messages ? implode(' ', $messages) : $e->getMessage());
        } catch (LocalizedException $e) {
            return $this->respond(true, $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->critical($e);
            return $this->respond(true, __('We can\'t save the customer.'));
        }
    }

    /**
     * Decode and validate the request body. Returns null when the request is not acceptable.
     */
    private function getPayload(): ?array
    {
        if (!$this->request->isXmlHttpRequest() || !$this->request->isPost()) {
            return null;
        }

        try {
            $data = $this->serializer->unserialize((string)$this->request->getContent());
        } catch (\InvalidArgumentException $exception) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        foreach (['firstname', 'lastname', 'email', 'password', 'password_confirmation'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                return null;
            }
        }

        return $data;
    }

    private function buildCustomer(array $data): \Magento\Customer\Api\Data\CustomerInterface
    {
        $store = $this->storeManager->getStore();

        return $this->customerFactory->create()
            ->setFirstname(trim((string)($data['firstname'] ?? '')))
            ->setLastname(trim((string)($data['lastname'] ?? '')))
            ->setEmail(trim((string)($data['email'] ?? '')))
            ->setWebsiteId((int)$store->getWebsiteId())
            ->setStoreId((int)$store->getId())
            ->setGroupId((int)$this->groupManagement->getDefaultGroup((int)$store->getId())->getId());
    }

    private function deleteCacheSessionCookie(): void
    {
        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata()->setPath('/');
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }
    }

    /**
     * @param bool $isError
     * @param Phrase|string $message
     * @param array $extra
     */
    private function respond(bool $isError, $message, array $extra = []): JsonResult
    {
        return $this->resultJsonFactory->create()->setData(
            ['errors' => $isError, 'message' => (string)$message] + $extra
        );
    }
}
