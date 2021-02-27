<?php
require "../bootstrap.php";

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );

$requestMethod = $_SERVER["REQUEST_METHOD"];

$settings = array();
include("/var/www/config/settings.php");

// Use $_SERVER["HTTP_X_FORWARDED_PORT"] & $_SERVER['HTTP_X_FORWARDED_PROTO']
\OneLogin\Saml2\Utils::setProxyVars(true);

switch ($uri[1]) {
    case 'sso':

        /**
         * SAMPLE Code to demonstrate how to initiate a SAML Authorization request
         *
         * When the user visits this URL, the browser will be redirected to the SSO
         * IdP with an authorization request. If successful, it will then be
         * redirected to the consume URL (specified in settings) with the auth
         * details.
         */

        session_start();

        $auth = new \OneLogin\Saml2\Auth($settings);

        if (!isset($_SESSION['samlUserdata'])) {
            $auth->login();
        } else {

            $attributes = $_SESSION['samlUserdata'];

            if (!empty($attributes)) {
                echo '<h1>User attributes:</h1>';
                echo '<table><thead><th>Name</th><th>Values</th></thead><tbody>';
                foreach ($attributes as $attributeName => $attributeValues) {
                    echo '<tr><td>'.htmlentities($attributeName).'</td><td><ul>';
                    foreach ($attributeValues as $attributeValue) {
                        echo '<li>'.htmlentities($attributeValue).'</li>';
                    }
                    echo '</ul></td></tr>';
                }
                echo '</tbody></table>';
                if (!empty($_SESSION['IdPSessionIndex'])) {
                    echo '<p>The SessionIndex of the IdP is: '.$_SESSION['IdPSessionIndex'].'</p>';
                }
                if (!empty($_SESSION['samlNameId'])) {
                    echo '<p>NameId is: '.$_SESSION['samlNameId'].'</p>';
                }

                echo "<p><a href='".$_ENV['BASE_URL']."/slo'>Logout</a>";
            } else {
                echo 'Attributes not found';
            }
        }

        break;
    case 'acs':

        /**
         *  SP Assertion Consumer Service Endpoint
         */

        session_start();

        $auth = new \OneLogin\Saml2\Auth($settings);

        $auth->processResponse();

        $errors = $auth->getErrors();

        if (!empty($errors)) {
            echo '<p>' . implode(', ', $errors) . '</p>';
            exit();
        }

        if (!$auth->isAuthenticated()) {
            echo '<p>Not authenticated</p>';
            exit();
        }

        $_SESSION['samlNameId'] = $auth->getNameId();
        $_SESSION['samlUserdata'] = $auth->getAttributes();
        $_SESSION['IdPSessionIndex'] = $auth->getSessionIndex();
        if (isset($_POST['RelayState']) && \OneLogin\Saml2\Utils::getSelfURL() != $_POST['RelayState']) {
            $auth->redirectTo($_POST['RelayState']);
        } else {
            header("Location: ".$_ENV['BASE_URL']."/sso");
        }

        break;
    case 'slo':

        /**
         * SAMPLE Code to demonstrate how to initiate a SAML Single Log Out request
         *
         * When the user visits this URL, the browser will be redirected to the SLO
         * IdP with an SLO request.
         */

        session_start();

        $samlSettings = new \OneLogin\Saml2\Settings($settings);

        $idpData = $samlSettings->getIdPData();
        if (isset($idpData['singleLogoutService']) && isset($idpData['singleLogoutService']['url'])) {
            $sloUrl = $idpData['singleLogoutService']['url'];
        } else {
            throw new Exception("The IdP does not support Single Log Out");
        }

        if (isset($_SESSION['IdPSessionIndex']) && !empty($_SESSION['IdPSessionIndex'])) {
            $logoutRequest = new \OneLogin\Saml2\LogoutRequest($samlSettings, null, $_SESSION['IdPSessionIndex']);
        } else {
            $logoutRequest = new \OneLogin\Saml2\LogoutRequest($samlSettings);
        }

        $samlRequest = $logoutRequest->getRequest();

        $parameters = array('SAMLRequest' => $samlRequest);

        $url = \OneLogin\Saml2\Utils::redirect($sloUrl, $parameters, true);

        header("Location: $url");

        break;
    case 'sls':

        /**
         *  SP Single Logout Service Endpoint
         */

        session_start();

        $auth = new \OneLogin\Saml2\Auth($settings);

        $auth->processSLO();

        $errors = $auth->getErrors();

        if (empty($errors)) {
            echo '<p>Sucessfully logged out</p>';
            echo '<p><a href="'.$_ENV['BASE_URL'].'">Overview</a>';

        } else {
            echo implode(', ', $errors);
        }

        break;

    case 'metadata':
        /**
         *  SP Metadata Endpoint
         */

        try {
            $auth = new \OneLogin\Saml2\Auth($settings);
            $settings = $auth->getSettings();
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                header('Content-Type: text/xml');
                echo $metadata;
            } else {
                throw new \OneLogin\Saml2\Error(
                    'Invalid SP metadata: '.implode(', ', $errors),
                    \OneLogin\Saml2\Error::METADATA_SP_INVALID
                );
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        break;
    default:
        echo "<p><a href='https://keycloak.burpisnotbeef.ovh/auth/realms/development/protocol/saml/clients/client1'>IDP initiated login</a></p>";
        echo "<p><a href='".$_ENV['BASE_URL']."/sso'>SP initiated login</a></p>";

        break;
}
