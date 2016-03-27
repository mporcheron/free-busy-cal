<?php

namespace Porcheron\FreeBusyCal;

/**
 * Container for a calendar's configuration details.
 *
 * @author Martin Porcheron <martin@porcheron.uk>
 * @copyright (c) Martin Porcheron 2016.
 * @license MIT Licence
 */

class Calendar extends \ArrayObject
{
    
    /**
     * @var mixed[] Configuration data.
     */
    private $data;

    /**
     * Create the calendar configuration with the default values.
     */
    public function __construct()
    {
        parent::__construct([], \ArrayObject::ARRAY_AS_PROPS);

        $this->offsetSet('username', '');
        $this->offsetSet('password', '');
        $this->offsetSet('uri', '');
        $this->offsetSet('hostname', '');
        $this->offsetSet('port', 80);
        $this->offsetSet('ssl', false);
    }

    /**
     * Set the username to connect with.
     *
     * @param string $username New username to login with. Leave blank to disable.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function setUsername($username = '')
    {
        $this->offsetSet('username', $username);
        return $this;
    }

    /**
     * Set the password to connect with.
     *
     * @param string $password New password to login with. Leave blank to disable.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function setPassword($password = '')
    {
        $this->offsetSet('password', $password);
        return $this;
    }

    /**
     * Set the URL to connect to.
     *
     * @param string $url New URL to connect to.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function setUrl($url)
    {
        $this->offsetSet('url', \filter_var($url, FILTER_SANITIZE_URL));
        $components = \parse_url($this->offsetGet('url'));

        if (isset($components['scheme']) && $components['scheme'] == 'https' ||
            $components['scheme'] == 'ssl' || $components['scheme'] == 'tls') {
            $this->offsetSet('ssl', true);
        }

        if (isset($components['hostname'])) {
            $this->offsetSet('host', $components['hostname']);
        } elseif (isset($components['host'])) {
            $this->offsetSet('host', $components['host']);
        }

        if (isset($components['port'])) {
            $this->offsetSet('port', $components['port']);
        }

        return $this;
    }

    /**
     * Connect with SSL?
     *
     * @param boolean $ssl Set to {@code true} to connect with SSL.
     * @return Porcheron\FreeBusyCal\Calendar {@code $this}.
     */
    public function enableSsl($ssl)
    {
        $this->offsetSet('ssl', \filter_var($ssl, FILTER_VALIDATE_BOOLEAN));
        return $this;
    }
}
