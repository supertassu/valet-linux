<?php

namespace Valet;

/**
 * warning: might contain humour
 * @author tassu
 */
class CertificateHelper
{
    public $cli, $files;

    /**
     * Create a new CertificateHelper instance.
     *
     * @param  CommandLine $cli
     * @param  Filesystem $files
     */
    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    public function __toString()
    {
        return '\Valet\CertificateHelper';
    }

    /**
     * Get the path to the Valet TLS certificates.
     *
     * @return string
     */
    public function certificatesPath()
    {
        return VALET_HOME_PATH . '/Certificates';
    }

    /**
     * Get the path to the Valet TLS certificates.
     *
     * @return string
     */
    public function caPath()
    {
        return VALET_HOME_PATH . '/CA';
    }

    /**
     * Ensures that the root certificate exists.
     */
    public function ensureRootCertificateExists()
    {
        $caPath = $this->caPath();
        $this->files->ensureDirExists($caPath, user());

        if (!$this->files->exists($caPath . '/valet-ca.crt')) {
            $this->createRootCertificate($caPath);
        }
    }

    /**
     * Creates a root certificate.
     *
     * @param  string $caPath CA folder
     */
    private function createRootCertificate($caPath)
    {
        $keyFile = $caPath . '/valet.key';
        $certFile = $caPath . '/valet-ca.crt';

        $this->cli->runAsUser(sprintf(
            'openssl genrsa -out %s 4096',
            $keyFile
        ));

        $this->cli->runAsUser(sprintf(
            'openssl req -x509 -new -nodes -key %s -sha256 -days 2048 -out %s -subj "/C=/ST=/O=%s/localityName=/commonName=%s/organizationalUnitName=Developers/emailAddress=%s/"',
            $keyFile, $certFile, 'Not A CA, Ltd', 'Not A Certificate Authority Limited', 'not-a-ca@tassu.me'
        ));
    }

    public function createCertificateForSite($url)
    {
        $this->ensureRootCertificateExists();

        $keyPath = $this->certificatesPath() . '/' . $url . '.key';
        $csrPath = $this->certificatesPath() . '/' . $url . '.csr';
        $crtPath = $this->certificatesPath() . '/' . $url . '.crt';
        $confPath = $this->certificatesPath() . '/' . $url . '.conf';

        $caKeyPath = $this->caPath() . '/valet.key';
        $caCertPath = $this->caPath() . '/valet-ca.crt';

        $this->buildCertificateConf($confPath, $url);
        $this->createKey($keyPath);
        $this->createCsr($url, $keyPath, $csrPath);
        $this->createCrt($csrPath, $crtPath, $confPath, $caCertPath, $caKeyPath);
    }

    private function createKey($keyPath)
    {
        $this->cli->runAsUser(sprintf(
            'openssl genrsa -out %s 2048',
            $keyPath
        ));
    }

    /**
     * Build the SSL config for the given URL.
     *
     * @param  string $path
     * @param  string $url
     * @return string
     */
    public function buildCertificateConf($path, $url)
    {
        $config = str_replace('VALET_DOMAIN', $url, $this->files->get(__DIR__ . '/../stubs/openssl.conf'));
        $this->files->putAsUser($path, $config);
    }

    private function createCsr($url, $keyPath, $csrPath)
    {
        $subject = sprintf(
            '/C=FI/ST=Uusimaa/O=Not A Business, Inc/localityName=Not A Business, Inc/CN=%s/organizationalUnitName=Development/emailAddress=not-a-business@tassu.me/',
            $url
        );

        $command = sprintf(
            'openssl req -new -newkey rsa:2048 -sha256 -nodes -key %s -subj "%s" -out %s',
            $keyPath, $subject, $csrPath
        );

        $this->cli->runAsUser($command);
    }

    private function createCrt($csrPath, $crtPath, $confPath, $caCertPath, $caKeyPath)
    {
        $command = sprintf(
            'openssl x509 -req -in %s -CA %s -CAkey %s -CAcreateserial -out %s -days 3650 -sha256 -extfile %s',
            $csrPath, $caCertPath, $caKeyPath, $crtPath, $confPath
        );

        $this->cli->runAsUser($command);
    }
}
