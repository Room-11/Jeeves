<?php declare(strict_types = 1);

namespace Room11\Jeeves;

const DNS_NAME_EXPR = '(?:(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?)\.)*(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?)';
const ROOM_IDENTIFIER_EXPR = '(' . DNS_NAME_EXPR . ')#([0-9]+)';
