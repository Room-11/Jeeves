<?php

namespace Room11\Jeeves;

/**
 * @param string $html
 * @param string $charSet
 * @param int $options
 * @return \DOMDocument
 * @throws LibXMLFatalErrorException
 */
function domdocument_load_html(string $html, string $charSet = 'UTF-8', int $options = 0): \DOMDocument
{
    if (!preg_match('#\s*<\?xml#i', $html)) {
        $html = '<?xml encoding="' . $charSet . '" ?>' . $html;
    }

    try {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($html, $options);

        /** @var \LibXMLError $error */
        foreach (libxml_get_errors() as $error) {
            if ($error->level === LIBXML_ERR_FATAL) {
                throw new LibXMLFatalErrorException($error);
            }
        }

        return $dom;
    } finally {
        libxml_use_internal_errors($internalErrors);
    }
}

/**
 * @param string[] $docs
 * @param callable $callback
 * @param string $charSet
 * @return \DOMDocument
 * @throws LibXMLFatalErrorException
 */
function domdocument_process_html_docs(array $docs, callable $callback, string $charSet = 'UTF-8'): \DOMDocument
{
    try {
        $internalErrors = libxml_use_internal_errors(true);

        foreach ($docs as $html) {
            if (!preg_match('#\s*<\?xml#i', $html)) {
                $html = '<?xml encoding="' . $charSet . '" ?>' . $html;
            }

            $dom = new \DOMDocument();
            $dom->loadHTML($html);

            /** @var \LibXMLError $error */
            foreach (libxml_get_errors() as $error) {
                if ($error->level === LIBXML_ERR_FATAL) {
                    throw new LibXMLFatalErrorException($error);
                }
            }

            if ($callback($dom) === false) {
                break;
            }
        }
    } finally {
        libxml_use_internal_errors($internalErrors);
    }
}
