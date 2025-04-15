<?php
namespace NOSE\IRI;

class IRI {
	public const regexVars = "(?(DEFINE)
	(?P<ALPHA>(?i:[a-z]))
	(?P<DIGIT>[0-9])
	(?P<HEXDIG>(?i:[0-9a-f]))
	(?P<subDelims>[!$&'()*+,;=])
	(?P<genDelims>[:\/?#\[\]@])
	(?P<reserved>(?P>subDelims)|(?P>genDelims))
	(?P<unreserved>(?P>ALPHA)|(?P>DIGIT)|[\-._~])
	(?P<pctEncoded>%(?P>HEXDIG){2})
	(?P<ucschar>[\x{A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}])
	(?P<iprivate>[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}])
	(?P<iunreserved>(?P>unreserved)|(?P>ucschar))
	(?P<ipchar>(?P>iunreserved)|(?P>pctEncoded)|(?P>subDelims)|[:@])
	)";

	public null | string $scheme {
		set(null | string $scheme) {
			if (empty($scheme)) {
				$this->scheme = null;
				return;
			}

			if (preg_match("/" . self::regexVars . "^(?P>ALPHA)(?:(?P>ALPHA)|(?P>DIGIT)|[+\-.])*$/uJs", $scheme) !== 1) throw new \InvalidArgumentException("Invalid scheme.");

			$this->scheme = mb_strtolower($scheme);
		}
	}
	public null | string $userinfo {
		set(null | string $userinfo) {
			if (empty($userinfo)) {
				$this->userinfo = null;
				return;
			}

			if (preg_match("/" . self::regexVars . "^(?:(?P>iunreserved)|(?P>pctEncoded)|(?P>subDelims)|:)*$/uJs", $userinfo) !== 1) throw new \InvalidArgumentException("Invalid userinfo.");

			$this->userinfo = self::normalizePercentEncoding($userinfo);
		}
	}
	public null | string $host {
		set(null | string $host) {
			if (empty($host)) {
				$this->host = null;
				return;
			}

			if (preg_match("/" . self::regexVars . "^(?:(?P>iunreserved)|(?P>pctEncoded)|(?P>subDelims))*|\[(?:(?P>unreserved)|(?P>subDelims)|:)+\]$/uJs", $host) !== 1) throw new \InvalidArgumentException("Invalid host.");

			$this->host = self::normalizePercentEncoding(mb_strtolower($host));
		}
	}
	public null | int $port {
		set(null | int | string $port) {
			if (empty($port)) {
				$this->port = null;
				return;
			}

			if (is_string($port)) $port = intval($port);

			if ($port < 0 || $port > 65535) throw new \InvalidArgumentException("Invalid port.");

			$this->port = $port;
		}
	}
	public null | string $path {
		set(null | string $path) {
			if (empty($path)) {
				$this->path = null;
				return;
			}

			if (preg_match("/" . self::regexVars . "^(?:\/?(?P>ipchar)*)*$/uJs", $path) !== 1) throw new \InvalidArgumentException("Invalid path.");

			$this->path = self::normalizePercentEncoding($path);
		}
	}
	public null | string $query {
		set(null | string $query) {
			if (empty($query)) {
				$this->query = null;
				return;
			}

			if (preg_match("/" . self::regexVars . "^(?:(?P>ipchar)|(?P>iprivate)|[\/?])*$/uJs", $query) !== 1) throw new \InvalidArgumentException("Invalid query.");

			$this->query = self::normalizePercentEncoding($query);
		}
	}
	public null | string $fragment {
		set(null | string $fragment) {
			if (empty($fragment)) {
				$this->fragment = null;
				return;
			}
			
			if (preg_match("/" . self::regexVars . "^(?:(?P>ipchar)|[\/?])*$/uJs", $fragment) !== 1) throw new \InvalidArgumentException("Invalid fragment.");

			$this->fragment = self::normalizePercentEncoding($fragment);
		}
	}

	public string $href {
		get {
			$res = "";

			if (!empty($this->scheme)) $res .= $this->scheme . ":";
			if (!empty($this->host)) {
				$res .= "//";

				if (!empty($this->userinfo)) $res .= $this->userinfo . "@";
				$res .= $this->host;
				if (!empty($this->port)) $res .= ":" . $this->port;
			}
			$res .= $this->path ?? "";
			if (!empty($this->query)) $res .= "?" . $this->query;
			if (!empty($this->fragment)) $res .= "#" . $this->fragment;

			return $res;
		}
		set(string $href) {
			$parts = self::parse(self::pctDecode($href, "%|(?P>iunreserved)"));

			$this->scheme = $parts["scheme"];
			$this->userinfo = $parts["userinfo"];
			$this->host = $parts["host"];
			$this->port = $parts["port"];
			$this->path = $parts["path"];
			$this->query = $parts["query"];
			$this->fragment = $parts["fragment"];
		}
	}
	public string $uri {
		get {
			return self::pctEncode($this->href, "%|(?P>unreserved)|(?P>reserved)");
		}
	}

	public bool $isAbsolute {
		get => !empty($this->scheme) && !empty($this->path) && !empty($this->query) && empty($this->fragment);
	}
	public bool $isRelative {
		get => empty($this->scheme);
	}

	public function resolve(IRI $base) {
		$target = new IRI();

		if ($base->isRelative) throw new \InvalidArgumentException("Base IRI cannot be relative.");

		if (!$this->isRelative) {
			$target->scheme = $this->scheme;
			$target->userinfo = $this->userinfo;
			$target->host = $this->host;
			$target->port = $this->port;
			$target->path = self::removeDotSegments($this->path);
			$target->query = $this->query;
		} else {
			if (!empty($this->host)) {
				$target->userinfo = $this->userinfo;
				$target->host = $this->host;
				$target->port = $this->port;
				$target->path = self::removeDotSegments($this->path);
				$target->query = $this->query;
			} else {
				if (empty($this->path)) {
					$target->path = $base->path;

					if (!empty($this->query)) $target->query = $this->query;
					else $target->query = $base->query;
				} else {
					if (($this->path ?? "")[0] === "/") $target->path = self::removeDotSegments($this->path);
					else $target->path = self::removeDotSegments(self::mergePaths($base->path, $this->path));

					$target->query = $this->query;
				}

				$target->userinfo = $base->userinfo;
				$target->host = $base->host;
				$target->port = $base->port;
			}

			$target->scheme = $base->scheme;
		}

		$target->fragment = null;

		return $target;
	}

	public function __construct(null | string $href = null) {
		if (!empty($href)) $this->href = $href;
	}

	protected static function parse(string $iri): array {
		if (preg_match("/" . self::regexVars . "^(?P<IRI>(?:(?P<scheme>(?P>ALPHA)(?:(?P>ALPHA)|(?P>DIGIT)|[+\-.])*):)?(?:\/\/(?:(?P<userinfo>(?:(?P>iunreserved)|(?P>pctEncoded)|(?P>subDelims)|:)*)@)?(?P<host>(?:(?P>iunreserved)|(?P>pctEncoded)|(?P>subDelims))*|\[(?:(?P>unreserved)|(?P>subDelims)|:)+\])(?::(?P<port>(?P>DIGIT)*))?)?(?P<path>(?:\/?(?P>ipchar)*)*)(?:\?(?P<query>(?:(?P>ipchar)|(?P>iprivate)|[\/?])*))?(?:#(?P<fragment>(?:(?P>ipchar)|[\/?])*))?)$/uJs", $iri, $matches, PREG_UNMATCHED_AS_NULL) !== 1) throw new \InvalidArgumentException("Invalid IRI.");

		$res = [];

		foreach (array_keys($matches) as $key) if (is_string($key)) $res[$key] = $matches[$key];

		return $res;
	}

	protected static function mergePaths(string $basePath, string $relPath): string {
		if (empty($basePath)) return "/" . $relPath;
		else return preg_replace(";(?:^|/)[^/]*$;uJs", "/", $basePath) . $relPath;
	}
	protected static function removeDotSegments(string $path): string {
		$resPath = "";

		while (mb_strlen($path) > 0) {
			if (preg_match(";^\.{1,2}/;uJs", $path) === 1) $path = preg_replace(";^\.{1,2}/;uJs", "", $path);
			else if (preg_match(";^/\.(?:/|$);uJs", $path) === 1) $path = preg_replace(";^/\.(?:/|$);uJs", "/", $path);
			else if (preg_match(";^/\.{2}(?:/|$);uJs", $path) === 1) {
				$path = preg_replace(";^/\.{2}(?:/|$);uJs", "/", $path);
				$resPath = preg_replace(";(?:^|/)[^/]*$;uJs", "", $resPath);
			}
			else if (preg_match(";^\.{1,2}$;uJs", $path) === 1) $path = preg_replace(";^\.{1,2}$;uJs", "", $path);
			else {
				preg_match(";^(/?[^/]*)(?:/|$);uJs", $path, $matches);

				[$tmp, $segment] = $matches;
				unset($matches, $tmp);

				$path = preg_replace(";^(/?[^/]*)(?:/|$);uJs", "", $path);
				$resPath .= $segment;
			}
		}

		return $resPath;
	}

	public static function pctEncode(string $str, null | string $allowedRegex = null): string {
		$allowedRegex ??= "(?P>iunreserved)";
		$allowedRegex = ";^" . str_replace(";", "\;", self::regexVars) . "(?:" . $allowedRegex . ")$;uJs";

		$str = mb_str_split($str);

		$res = "";

		foreach ($str as $char) {
			if (preg_match($allowedRegex, $char) === 1) {
				$res .= $char;
				continue;
			}

			$bytes = str_split($char);
			foreach ($bytes as $byte) {
				$res .= "%" . mb_strtoupper(dechex(ord($byte)));
			}
		}

		return self::normalizePercentEncoding($res);
	}
	public static function pctDecode(string $str, null | string $allowedRegex = null): string {
		$allowedRegex ??= "(?P>iunreserved)";
		$origAllowedRegex = $allowedRegex;
		$allowedRegex = ";^(?:" . self::regexVars . $allowedRegex . ")$;uJs";

		$str = mb_str_split($str);

		$res = "";

		$fullChar = "";

		for ($index = 0; $index < count($str); $index++) {
			if ($char !== "%") continue;

			$octet = array_slice($str, $index, 3);
			if (preg_match("/" . self::regexVars . "(?P>pctEncoded)/uJs", implode("", $octet)) !== 1) {
				$res .= $char;
				continue;
			}

			$byte = hexdec(implode("", array_slice($octet, 1, 2)));
			if ($byte & 0b11000000 === 0b11000000) {
				if (!empty($fullChar)) {
					if (preg_match($allowedRegex, $fullChar) === 1) {
						$res .= $fullChar;
						$fullChar = "";
					}
					else self::pctEncode($fullChar, $origAllowedRegex);
				}

				$fullChar = chr($byte);
			} else if ($byte & 0b11000000 === 0b10000000) $fullChar .= chr($byte);
			else if ($byte & 0b10000000 === 0) {
				$tmpChar = chr($byte);

				if (preg_match($allowedRegex, $tmpChar) === 1) $res .= $tmpChar;
				else self::pctEncode($tmpChar, $origAllowedRegex);
			}

			$index += 2;
		}

		return self::normalizePercentEncoding($res);
	}
	protected static function normalizePercentEncoding(string $pctStr): string {
		return preg_replace_callback("/%([a-f0-9]{2})/uJsi", function ($matches) {
			return "%" . mb_strtoupper($matches[1]);
		}, $pctStr);
	}
}
?>