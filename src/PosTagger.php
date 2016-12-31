<?php
/**
 * http://phpir.com/part-of-speech-tagging/
 *
 * Part Of Speech Tagging
 * Brill Tagger
 *
 * Requires reading the lexicon.txt file.
 * The tags are listed here:
 * https://en.wikipedia.org/wiki/Brown_Corpus#Part-of-speech_tags_used
 *
 */

namespace Room11\Jeeves;

use Amp\Success;
use Room11\Jeeves\Storage\File\FileAccessor;

class PosTagger
{
    private $dictionary;

    public function setupDictionary(){
        $accessor = new FileAccessor();
        $file  = yield $accessor->read("src/lexicon.txt");
        $lines = explode(PHP_EOL, $file);

        foreach ($lines as $key => $line){
            $tags = explode(' ', $line);
            $this->dictionary[strtolower(array_shift($tags))] = $tags;
        }

        return new Success();
    }

    public function tag($text) {

        if(!$this->dictionary) yield from $this->setupDictionary();

        preg_match_all("/[\w\d\.]+/", $text, $matches);
        $nouns  = ['NN', 'NNS'];
        $tags = [];
        $i = 0;
        foreach($matches[0] as $token) {
            # default to a common noun
            $tags[$i] = ['token' => $token, 'tag' => 'NN'];

            # remove trailing full stops
            if(substr($token, -1) == '.') {
                $token = preg_replace('/\.+$/', '', $token);
            }

            # get from dictionary if set
            if(isset($this->dictionary[strtolower($token)])) {
                $tags[$i]['tag'] = $this->dictionary[strtolower($token)][0];
            }

            # Converts verbs after 'the' to nouns
            if($i > 0) {
                if($tags[$i - 1]['tag'] == 'DT' && in_array($tags[$i]['tag'], ['VBD', 'VBP', 'VB'])) {
                    $tags[$i]['tag'] = 'NN';
                }
            }

            # Convert noun to number if . appears
            if($tags[$i]['tag'][0] == 'N' && strpos($token, '.') !== false) {
                $tags[$i]['tag'] = 'CD';
            }

            # Convert noun to past participle if ends with 'ed'
            if($tags[$i]['tag'][0] == 'N' && substr($token, -2) == 'ed') {
                $tags[$i]['tag'] = 'VBN';
            }

            # Anything that ends 'ly' is an adverb
            if(substr($token, -2) == 'ly') {
                $tags[$i]['tag'] = 'RB';
            }

            # Common noun to adjective if it ends with al
            if(in_array($tags[$i]['tag'], $nouns) && substr($token, -2) == 'al') {
                $tags[$i]['tag'] = 'JJ';
            }

            # Noun to verb if the word before is 'would'
            if($i > 0) {
                if($tags[$i]['tag'] == 'NN' && strtolower($tags[$i-1]['token']) == 'would') {
                    $tags[$i]['tag'] = 'VB';
                }
            }

            # Convert noun to plural if it ends with an s
            if($tags[$i]['tag'] == 'NN' && substr($token, -1) == 's') {
                $tags[$i]['tag'] = 'NNS';
            }

            # Convert common noun to gerund
            if(in_array($tags[$i]['tag'], $nouns) && substr($token, -3) == 'ing') {
                $tags[$i]['tag'] = 'VBG';
            }

            # If we get noun noun, and the second can be a verb, convert to verb
            if($i > 0) {

                if( in_array($tags[$i]['tag'], $nouns)
                    && in_array($tags[$i-1]['tag'], $nouns)
                    && isset($this->dictionary[strtolower($token)])
                ) {
                    if(in_array('VBN', $this->dictionary[strtolower($token)])) {
                        $tags[$i]['tag'] = 'VBN';
                    } else if(in_array('VBZ', $this->dictionary[strtolower($token)])) {
                        $tags[$i]['tag'] = 'VBZ';
                    }
                }
            }

            $i++;
        }

        return $tags;
    }
}
