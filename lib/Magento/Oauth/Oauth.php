<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Oauth;

class Oauth implements OauthInterface
{
    /** @var  \Magento\Oauth\Helper\Oauth */
    protected $_oauthHelper;

    /** @var  \Zend_Oauth_Http_Utility */
    protected $_httpUtility;

    /** @var \Magento\Oauth\NonceGeneratorInterface */
    protected $_nonceGenerator;

    /** @var \Magento\Oauth\TokenProviderInterface */
    protected $_tokenProvider;

    /**
     * @param Helper\Oauth $oauthHelper
     * @param NonceGeneratorInterface $nonceGenerator
     * @param TokenProviderInterface $tokenProvider
     * @param \Zend_Oauth_Http_Utility $httpUtility
     */
    public function __construct(
        Helper\Oauth $oauthHelper,
        NonceGeneratorInterface $nonceGenerator,
        TokenProviderInterface $tokenProvider,
        \Zend_Oauth_Http_Utility $httpUtility
    ) {
        $this->_oauthHelper = $oauthHelper;
        $this->_nonceGenerator = $nonceGenerator;
        $this->_tokenProvider = $tokenProvider;
        $this->_httpUtility = $httpUtility;
    }

    /**
     * Retrieve array of supported signature methods.
     *
     * @return array - Supported HMAC-SHA1 and HMAC-SHA256 signature methods.
     */
    public static function getSupportedSignatureMethods()
    {
        return array(self::SIGNATURE_SHA1, self::SIGNATURE_SHA256);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestToken($params, $requestUrl, $httpMethod = 'POST')
    {
        $this->_validateVersionParam($params['oauth_version']);
        $consumer = $this->_tokenProvider->getConsumerByKey($params['oauth_consumer_key']);
        $this->_tokenProvider->validateConsumer($consumer);
        $this->_nonceGenerator->validateNonce($consumer, $params['oauth_nonce'], $params['oauth_timestamp']);

        $this->_validateSignature(
            $params,
            $consumer->getSecret(),
            $httpMethod,
            $requestUrl
        );

        return $this->_tokenProvider->createRequestToken($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken($params, $requestUrl, $httpMethod = 'POST')
    {
        $required = array(
            'oauth_consumer_key',
            'oauth_signature',
            'oauth_signature_method',
            'oauth_nonce',
            'oauth_timestamp',
            'oauth_token',
            'oauth_verifier'
        );

        $this->_validateProtocolParams($params, $required);
        $consumer = $this->_tokenProvider->getConsumerByKey($params['oauth_consumer_key']);
        $tokenSecret = $this->_tokenProvider
            ->validateRequestToken($params['oauth_token'], $consumer, $params['oauth_verifier']);

        $this->_validateSignature(
            $params,
            $consumer->getSecret(),
            $httpMethod,
            $requestUrl,
            $tokenSecret
        );

        return $this->_tokenProvider->getAccessToken($consumer);
    }

    /**
     * {@inheritdoc}
     */
    public function validateAccessTokenRequest($params, $requestUrl, $httpMethod = 'POST')
    {
        $required = array(
            'oauth_consumer_key',
            'oauth_signature',
            'oauth_signature_method',
            'oauth_nonce',
            'oauth_timestamp',
            'oauth_token'
        );

        $this->_validateProtocolParams($params, $required);
        $consumer = $this->_tokenProvider->getConsumerByKey($params['oauth_consumer_key']);
        $tokenSecret = $this->_tokenProvider->validateAccessTokenRequest($params['oauth_token'], $consumer);

        $this->_validateSignature(
            $params,
            $consumer->getSecret(),
            $httpMethod,
            $requestUrl,
            $tokenSecret
        );

        return $consumer->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function validateAccessToken($accessToken)
    {
        return $this->_tokenProvider->validateAccessToken($accessToken);
    }

    /**
     * {@inheritdoc}
     */
    public function buildAuthorizationHeader(
        $params, $requestUrl, $signatureMethod = self::SIGNATURE_SHA1, $httpMethod = 'POST'
    ) {
        $required = array(
            "oauth_consumer_key",
            "oauth_consumer_secret",
            "oauth_token",
            "oauth_token_secret"
        );
        $this->_checkRequiredParams($params, $required);
        $consumer = $this->_tokenProvider->getConsumerByKey($params['oauth_consumer_key']);
        $headerParameters = array(
            'oauth_nonce' => $this->_nonceGenerator->generateNonce($consumer),
            'oauth_timestamp' => $this->_nonceGenerator->generateTimestamp(),
            'oauth_version' => '1.0',
        );
        $headerParameters = array_merge($headerParameters, $params);
        $headerParameters['oauth_signature'] = $this->_httpUtility->sign(
            $params,
            $signatureMethod,
            $headerParameters['oauth_consumer_secret'],
            $headerParameters['oauth_token_secret'],
            $httpMethod,
            $requestUrl
        );
        $authorizationHeader = $this->_httpUtility->toAuthorizationHeader($headerParameters);
        // toAuthorizationHeader adds an optional realm="" which is not required for now.
        // http://tools.ietf.org/html/rfc2617#section-1.2
        return str_replace('realm="",', '', $authorizationHeader);
    }

    /**
     * Validate signature based on the signature method used.
     *
     * @param array $params
     * @param string $consumerSecret
     * @param string $httpMethod
     * @param string $requestUrl
     * @param string $tokenSecret
     * @throws \Magento\Oauth\Exception
     */
    protected function _validateSignature($params, $consumerSecret, $httpMethod, $requestUrl, $tokenSecret = null)
    {
        if (!in_array($params['oauth_signature_method'], self::getSupportedSignatureMethods())) {
            throw new Exception(
                __('Signature method %1 is not supported', $params['oauth_signature_method']),
                self::ERR_SIGNATURE_METHOD_REJECTED
            );
        }

        $allowedSignParams = $params;
        unset($allowedSignParams['oauth_signature']);

        $calculatedSign = $this->_httpUtility->sign(
            $allowedSignParams,
            $params['oauth_signature_method'],
            $consumerSecret,
            $tokenSecret,
            $httpMethod,
            $requestUrl
        );

        if ($calculatedSign != $params['oauth_signature']) {
            throw new Exception(__('Invalid signature'), self::ERR_SIGNATURE_INVALID);
        }
    }

    /**
     * Validate oauth version.
     *
     * @param string $version
     * @throws \Magento\Oauth\Exception
     */
    protected function _validateVersionParam($version)
    {
        // validate version if specified
        if ('1.0' != $version) {
            throw new Exception(__('OAuth version %1 is not supported', $version), self::ERR_VERSION_REJECTED);
        }
    }

    /**
     * Validate request and header parameters.
     *
     * @param array $protocolParams
     * @param array $requiredParams
     * @throws \Magento\Oauth\Exception
     */
    protected function _validateProtocolParams($protocolParams, $requiredParams)
    {
        // validate version if specified.
        if (isset($protocolParams['oauth_version'])) {
            $this->_validateVersionParam($protocolParams['oauth_version']);
        }

        // Required parameters validation. Default to minimum required params if not provided.
        if (empty($requiredParams)) {
            $requiredParams = array(
                "oauth_consumer_key",
                "oauth_signature",
                "oauth_signature_method",
                "oauth_nonce",
                "oauth_timestamp"
            );
        }
        $this->_checkRequiredParams($protocolParams, $requiredParams);

        if (isset($protocolParams['oauth_token']) &&
            !$this->_tokenProvider->validateOauthToken($protocolParams['oauth_token'])
        ) {
            throw new Exception(__('Token is not the correct length'), self::ERR_TOKEN_REJECTED);
        }

        // Validate signature method.
        if (!in_array($protocolParams['oauth_signature_method'], self::getSupportedSignatureMethods())) {
            throw new Exception(
                __('Signature method %1 is not supported', $protocolParams['oauth_signature_method']),
                self::ERR_SIGNATURE_METHOD_REJECTED
            );
        }

        $consumer = $this->_tokenProvider->getConsumerByKey($protocolParams['oauth_consumer_key']);
        $this->_nonceGenerator->validateNonce(
            $consumer, $protocolParams['oauth_nonce'], $protocolParams['oauth_timestamp']
        );
    }

    /**
     * Check if mandatory OAuth parameters are present.
     *
     * @param array $protocolParams
     * @param array $requiredParams
     * @throws \Magento\Oauth\Exception
     */
    protected function _checkRequiredParams($protocolParams, $requiredParams)
    {
        foreach ($requiredParams as $param) {
            if (!isset($protocolParams[$param])) {
                throw new Exception($param, self::ERR_PARAMETER_ABSENT);
            }
        }
    }
}
