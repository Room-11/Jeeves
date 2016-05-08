<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Filter;

class Parser
{
    const STATE_FIELD_NAME_START = 0;
    const STATE_FIELD_NAME       = 1;
    const STATE_FIELD_NAME_END   = 2;
    const STATE_VALUE_START      = 3;
    const STATE_VALUE            = 4;
    const STATE_VALUE_END        = 5;
    const STATE_SET_MEMBER_START = 6;
    const STATE_SET_MEMBER       = 7;
    const STATE_SET_MEMBER_END   = 8;
    const STATE_EXPECT_BOUNDARY  = 9;

    public function parse(string $string): array
    {
        $ptr = 0;
        $position = -1;
        $line = 1;
        $length = strlen($string);
        $conditions = [];

        boundary:
        {
            $state = self::STATE_FIELD_NAME_START;
            $fieldName = '';
            $valueType = Condition::VALUE_TYPE_SCALAR;
            $scalarValue = '';
            $setName = '';
            $setMember = '';
            $setMembers = [];

            // fall through
        }

        get_char:
        {
            if (++$ptr >= $length) {
                goto condition_end;
            }

            $char = $string[$ptr];

            if ($char === "\n") {
                $line++;
                $position = 0;
            } else {
                $position++;
            }

            switch ($state) {
                case self::STATE_FIELD_NAME_START: goto field_name_start;
                case self::STATE_FIELD_NAME:       goto field_name;
                case self::STATE_FIELD_NAME_END:   goto field_name_end;
                case self::STATE_VALUE_START:      goto value_start;
                case self::STATE_VALUE:            goto value;
                case self::STATE_VALUE_END:        goto value_end;
                case self::STATE_SET_MEMBER_START: goto set_member_start;
                case self::STATE_SET_MEMBER:       goto set_member;
                case self::STATE_SET_MEMBER_END:   goto set_member_end;
                case self::STATE_EXPECT_BOUNDARY:  goto expect_boundary;
            }

            throw new ParseErrorException('Invalid parser state ' . $state . ' at position ' . ($ptr - 1));
        }

        field_name_start:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char === '=') {
                $this->syntaxError('Empty field name', $line, $position);
            }

            if ($char === '&') {
                $this->syntaxError('Empty condition', $line, $position);
            }

            if (!(($char >= '0' && $char <= '9') || $char === '_')) {
                $char |= "\x20"; // lowercase the char

                if ($char < 'a' || $char > 'z') {
                    $this->syntaxError('Only 0-9a-z_ are allowed in field names', $line, $position);
                }
            }

            $state = self::STATE_FIELD_NAME;
            $fieldName .= $char;
            goto get_char;
        }

        field_name:
        {
            if ($char === '=') {
                $state = self::STATE_VALUE_START;
                goto get_char;
            }

            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                $state = self::STATE_FIELD_NAME_END;
                goto get_char;
            }

            if (!(($char >= '0' && $char <= '9') || $char === '_')) {
                $char |= "\x20"; // lowercase the char

                if ($char < 'a' || $char > 'z') {
                    $this->syntaxError('Only 0-9a-z_ are allowed in field names', $line, $position);
                }
            }

            $fieldName .= $char;
            goto get_char;
        }

        field_name_end:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char === '=') {
                $state = self::STATE_VALUE_START;
                goto get_char;
            }

            $this->syntaxError('Unexpected data after field name', $line, $position);
        }

        value_start:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char === "'" || $char === '"') {
                $scalarValue = $this->getQuotedString($string, $ptr, $length, $line, $position);
                $state = self::STATE_EXPECT_BOUNDARY;
                goto get_char;
            }

            if ($char === '(') {
                $this->syntaxError('Unexpected set member list start', $line, $position);
            }

            $state = self::STATE_VALUE;
            $scalarValue .= $char;
            goto get_char;
        }

        value:
        {
            if ($char === '&') {
                goto condition_end;
            }

            if ($char === '(') {
                $valueType = Condition::VALUE_TYPE_SET;
                $state = self::STATE_SET_MEMBER_START;
                $setName = $scalarValue;
                $scalarValue = '';
                goto get_char;
            }

            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                $state = self::STATE_VALUE_END;
                goto get_char;
            }

            $scalarValue .= $char;
            goto get_char;
        }

        value_end:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char === '&') {
                goto condition_end;
            }

            if ($char === '(') {
                $valueType = Condition::VALUE_TYPE_SET;
                $state = self::STATE_SET_MEMBER_START;
                $setName = $scalarValue;
                $scalarValue = '';
                goto get_char;
            }

            $this->syntaxError('Unexpected data after value', $line, $position);
        }

        set_member_start:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char === ',') {
                $this->syntaxError('Empty set member', $line, $position);
            }

            if ($char === "'" || $char === '"') {
                $setMember = $this->getQuotedString($string, $ptr, $length, $line, $position);
                $state = self::STATE_SET_MEMBER_END;
                goto get_char;
            }

            $setMember .= $char;
            $state = self::STATE_SET_MEMBER;
            goto get_char;
        }

        set_member:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                $state = self::STATE_SET_MEMBER_END;
                goto get_char;
            }

            if ($char === ',') {
                $setMembers[] = $setMember;
                $setMember = '';
                $state = self::STATE_SET_MEMBER_START;
                goto get_char;
            }

            if ($char === ')') {
                $setMembers[] = $setMember;
                $setMember = '';
                $state = self::STATE_EXPECT_BOUNDARY;
                goto get_char;
            }

            if (!(($char >= '0' && $char <= '9') || $char === '_')) {
                $char |= "\x20"; // lowercase the char

                if ($char < 'a' || $char > 'z') {
                    $this->syntaxError('Only 0-9a-z_ are allowed in unquoted set members, got ' . $char, $line, $position);
                }
            }

            $setMember .= $char;
            goto get_char;
        }

        set_member_end:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char === ',') {
                $setMembers[] = $setMember;
                $setMember = '';
                $state = self::STATE_SET_MEMBER_START;
                goto get_char;
            }

            if ($char === ')') {
                $setMembers[] = $setMember;
                $setMember = '';
                $state = self::STATE_EXPECT_BOUNDARY;
                goto get_char;
            }

            $this->syntaxError('Unexpected data after set member', $line, $position);
        }

        expect_boundary:
        {
            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                goto get_char;
            }

            if ($char !== '&') {
                $this->syntaxError('Expecting condition boundary, got "' . $char . '" instead', $line, $position);
            }

            // fall through
        }

        condition_end:
        {
            if ($fieldName !== '') {
                $conditions[] = new Condition($fieldName, $valueType, $scalarValue, $setName, $setMembers);
            }

            if ($ptr < $length) {
                goto boundary;
            }

            return $conditions;
        }
    }

    private function syntaxError($message, $line, $position)
    {
        throw new ParseErrorException("Syntax error: {$message} on line {$line} at position {$position}");
    }

    /**
     * @param string $string
     * @param int $ptr
     * @param int $length
     * @param $line
     * @param $position
     * @return string
     * @throws ParseErrorException
     */
    private function getQuotedString($string, &$ptr, $length, &$line, &$position)
    {
        $quote = $string[$ptr];
        $result = '';

        while (++$ptr < $length) {
            $char = $string[$ptr];

            if ($char === '\n') {
                $line++;
                $position = 0;
            } else {
                $position++;
            }

            if ($char === $quote) {
                $ptr++;
                return $result;
            }

            if ($char === '\\') {
                $escapedChar = $string[++$ptr];

                if ($escapedChar !== '\\' && $escapedChar !== $quote) {
                    $this->syntaxError('Invalid quoted string escape sequence', $line, $position);
                }
            }

            $result .= $string[$ptr];
        }

        $this->syntaxError('Unclosed quoted string', $line, $position);
        return '';
    }
}
