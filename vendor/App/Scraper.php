<?php
namespace App;

use Zend, Com;
class Scraper
{

    protected $errorReason;
    protected $phrases;
    protected $url;

    protected $clientUri;
    protected $theUrl;
    protected $response;


    function __construct()
    {
        ;
    }


    function setPhrases(array $phrases)
    {
        $this->phrases = $phrases;
        return $this;
    }

    function addPhrase($phrase)
    {
        $this->phrases[] = $phrase;
        return $this;
    }

    function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }


    function isOk()
    {
        $r = true;
        $this->errorReason = '';
        $content = $this->_scrapContent($this->url);

        if(!$content)
        {
            $r = false;
        }
        else
        {
            if($this->phrases)
            {
                foreach($this->phrases as $phrase)
                {
                    $found = stripos($content, $phrase);
                    if($found !== false)
                    {
                        $this->_setErrorReasonDetails("Phrase '$phrase' was found within the page content");
                        $r = false;
                        break;
                    }
                }
            }
        }
        
        return $r;
    }


    function getErrorReason()
    {
        return $this->errorReason;
    }


    protected function _scrapContent($clientPageUrl)
    {
        if(!substr($clientPageUrl, 0, 4) != 'www')
        {
            $clientPageUrl = "www.$clientPageUrl";
        }

        $content = false;
        
        $clientPageUrl = "http://$clientPageUrl";
        $this->clientUri = new Zend\Uri\Http($clientPageUrl);
        $this->theUrl = "$this->clientUri";

        try
        {
            $httpClient = new Zend\Http\Client($this->clientUri, array(
                'maxredirects' => 0,
                'timeout'      => 30,
                'useragent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.1 Safari/537.36',
                'adapter' => 'Zend\Http\Client\Adapter\Curl',
            ));

            $this->response = $httpClient->send();

            // we allow redirection only if is to change schema from http to https
            if($this->response->isRedirect() && $this->response->getHeaders()->has('location'))
            {
                $localtion = $this->response->getHeaders()->get('location');
                $uri = $localtion->uri();
                if(($uri->getHost() == $this->clientUri->getHost()) && ($uri->getPath() == $this->clientUri->getPath()) && ($uri->getQuery() == $this->clientUri->getQuery()))
                {
                    if($uri->getScheme() != $this->clientUri->getScheme())
                    {
                        // redirection allowed so we are going to do the request again
                        $this->theUrl = "$uri";
                        $httpClient->setUri($uri);
                        $this->response = $httpClient->send();
                        $content = $this->response->getBody();
                    }
                    else
                    {
                        $this->_setErrorReasonDetails("Redirection detected to $uri");
                    }
                }
                else
                {
                    $this->_setErrorReasonDetails("Redirection detected to $uri");
                }
            }
            else
            {
                if($this->response->isOk())
                {
                    $content = $this->response->getBody();
                }
                else
                {
                    $this->_setErrorReasonDetails("Invalid response code detected: {$response->getStatusCode()}");
                }
            }

            if(empty($content) && empty($this->errorReason))
            {
                $this->_setErrorReasonDetails("The page is empty");
            }
        }
        catch(\Exception $e)
        {
            $this->_setErrorReasonDetails($e->getmessage());
            $content = false;
        }

        return $content;
    }


    protected function _setErrorReasonDetails($e)
    {
        $this->errorReason = $e;
        $this->errorReason .= "<br><strong>Scrapped url:</strong> $this->theUrl";

        $this->errorReason .= "<br><br><strong>TECHNICAL INFO</strong>";
        $this->errorReason .= "<br><strong>Original url:</strong> $this->clientUri";
        if($this->response)
        {
            $this->errorReason .= "<br><strong>Response status:</strong> {$this->response->renderStatusLine()}";
            $this->errorReason .= "<br><strong>Http version:</strong> {$this->response->getVersion()}";
        }
    }
}