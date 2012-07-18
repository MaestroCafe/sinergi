<?php

/**
 * Sinergi is an open source application development framework for PHP
 *
 * Requires PHP version 5.4
 *
 * LICENSE: This source file is subject to the GNU General Public License 
 * version 2.0 (GPL-2.0) that is bundled with this package in the file 
 * LICENSE-GPL.txt and is available through the world-wide-web at the 
 * following URI: http://www.opensource.org/licenses/GPL-2.0. If you did 
 * not receive a copy of the GNU General Public License version 2.0 and are 
 * unable to obtain it through the web, please send a note to admin@sinergi.co 
 * so we can mail you a copy immediately.
 *
 * @package		sinergi
 * @author		Sinergi Team
 * @copyright	2010-2012 Sinergi Team
 * @license		http://www.opensource.org/licenses/GPL-2.0 GNU General Public License version 2.0 (GPL-2.0)
 * @link		https://github.com/sinergi/sinergi
 * @since		Version 1.0
 */
 
/**
 * Selectors contains the functions necessary to select DOM objects using CSS 3's patterns.
 * For the information on the CSS 3's patterns, please refer to the W3C's documentation
 * avalaible at http://www.w3.org/TR/css3-selectors/.
 *
 * Known bugs
 * - nth-child() is not working as specified by the W3C
 * 
 * Futur Additions:
 * - E:nth-last-child(n)
 * - E:nth-of-type(n)
 * - E:nth-last-of-type(n)
 * - Pseudos even and odd should be deprecated
 * - Everything here: http://www.w3.org/TR/css3-selectors/#structural-pseudos
 *
 * TO BE CLEANED UP IN A FUTURE RELEASE OF SINERGI.
 *
 * @category	core
 * @package		sinergi
 * @author		Sinergi Team
 * @link		https://github.com/sinergi/sinergi
 */
 
function selector($needle, $haystack) {	
	if (property_exists($haystack, 'element')) $haystack = $haystack->element;
	/**
 * Set operator. 
 *
 */
	$operator = strstr($needle, '=') ? preg_replace("/(.*[^\*|\^|\$|\!|\~|\|])([\*|\^|\$|\!|\~|\|]*\=)(.*)/i", "$2", $needle) : '=';
	/**
 * Get all infos between . [ ] =. 
 *
 */
	$split = preg_split("/\.|\#|\[|\]|\:|\(|\)/i", str_replace($operator, '.', substr($needle, 0, (strstr($needle, ':') ? stripos($needle, ':') : strlen($needle)))), -1, PREG_SPLIT_DELIM_CAPTURE);
	/**
 * Check for pseudos. 
 *
 */
	if (stripos($needle, ':')!==false) {
		$pseudo = explode(':', $needle);
		$operator2 = strstr($pseudo[1], '=') ? preg_replace("/(.*[^\*|\^|\$|\!|\~|\|])([\*|\^|\$|\!|\~|\|]*\=)(.*)/i", "$2", $pseudo[1]) : '.';
		$pseudo = array_values(array_filter(preg_split("/\.|\[|\]|\(|\)|\=/i", $pseudo[1], -1, PREG_SPLIT_DELIM_CAPTURE)));
	}
	
	/**
 * Tag is the first one. 
 *
 */
	$tag = $split[0];	
	/**
 * Return true if tag is match and only selector. 
 *
 */
	if (count($split)==1 and isset($haystack->tagName) and $haystack->tagName==$tag and !isset($pseudo)) return true;
	/**
 * Return false if tag is not matched. 
 *
 */
	else if ($haystack->nodeName!=$tag and $tag!='*' and $tag!='') return false;
	
	/**
 * Check seperators. 
 *
 */
	/**
 * Set property (if nothing it's 'class' or 'id') and its value. 
 *
 */
	if (isset($split[3]) and $split[3]!='') {
		if (!operator($haystack, $operator, (strstr($needle, '#') ? 'id' : 'class-fixed-tmp'), $split[1])) return false;
		if (!operator($haystack, $operator, $split[2], $split[3])) return false;
	} else if (isset($split[2]) and $split[2]!='') {
		if (!operator($haystack, $operator, $split[1], $split[2])) return false;
	} else if (isset($split[1])) {
		if (!operator($haystack, $operator, (strstr($needle, '#') ? 'id' : 'class-fixed-tmp'), $split[1])) return false;
	}
	/**
 * Else keep going. 
 *
 */
		
	/**
 * Check pseudos. 
 *
 */
	if (isset($pseudo)) return pseudo($haystack, $pseudo[0], $operator2, (isset($pseudo[1]) ? $pseudo[1] : null), (isset($pseudo[2]) ? $pseudo[2] : null), $tag);
	/**
 * Else return true has we have made this far without a return false;. 
 *
 */
	else return true;
}

//if (!isset($property) or empty($property)) return true;
function operator($haystack, $operator, $property, $value) {
	if ($property=='class-fixed-tmp') return (new el($haystack))->hasClass($value);
	switch ($operator) {
		case '=': /*is equal to*/return $haystack->getAttribute($property)==$value ? true : false; break;
		case '*=': /*contains*/ return stristr($haystack->getAttribute($property), $value) ? true : false; break;
		case '^=': /*starts-with*/ return substr_compare($haystack->getAttribute($property), $value, 0, strlen($value), true) ? false : true; break;
		case '$=': /*ends-with*/ return substr_compare($haystack->getAttribute($property), $value, -strlen($value), strlen($value), true) ? false : true; break;
		case '!=': /*is not equal to*/ return $haystack->getAttribute($property)!=$value ? true : false; break;
		case '~=': /*contained in a space separated list*/
			$list = explode(' ', strtolower($haystack->getAttribute($property)));
			return in_array(strtolower($value), $list) ? true : false;
			break;
		case '|=': /*contained in a '-' separated list*/
			$list = explode('-', strtolower($haystack->getAttribute($property)));
			return in_array(strtolower($value), $list) ? true : false;
			break;
	}
}

function pseudo($haystack, $pseudo, $operator, $match1=null, $match2=null, $tag=null)	{
	global $odd, $even;
	switch ($pseudo) {
		case 'checked': return $haystack->hasAttribute($pseudo) ? true : false; break;
		case 'enabled': return $haystack->hasAttribute('disabled') ? false : true; break;
		case 'empty': return $haystack->hasChildNodes() ? false : true; break;
		case 'contains': return strstr($haystack->nodeValue, str_replace('"', '', $match1)) ? true : false; break;
		case 'not':
			/**
 * If only looking for tag name. 
 *
 */
			if ($match2==null&&$haystack->tagName==$match1) return false;
			else if ($match2==null) return true;
			/**
 * Else check for class. 
 *
 */
			if ($operator=='.') {
				if ($haystack->tagName==$match1&&operator($haystack, 'class-fixed-tmp', $match2)) return false;
			/**
 * Else check for other property. 
 *
 */
			} else if (operator($haystack, $operator, $match1, $match2)) {
				return false;
			}
			/**
 * Else it was not found therefore true. 
 *
 */
			else return true;
			break;
		case 'odd':
			if (!isset($odd)) $odd = 0;
			if (($odd++)%2==0) return true;
			else return false;
			break;
		case 'even':
			if (!isset($even)) $even = 0;
			if ((($even++)+1)%2==0) return true;
			else return false;
			break;
		case 'first-child':
			return $haystack->parentNode->firstChild->isSameNode($haystack);
			break;
		case 'last-child':
			return $haystack->parentNode->lastChild->isSameNode($haystack);
			break;
		case 'only-child':
			/**
 * Check all parent's children. If found a matching tag that is not him, return false. 
 *
 */
			foreach($haystack->parentNode->childNodes as $node)
				if ($haystack->tagName==$node->tagName and !$haystack->isSameNode($node)) return false;
			/**
 * If no return was made, then it's an only child. 
 *
 */
			return true;
			break;
		case 'nth-child':
			/**
 * match1 has the hint, if no 'n', false automatically. 
 *
 */
			if (!strstr($match1, 'n')) return false;
			if ($match1=='n') return true;
			/**
 * Get node number element. 
 *
 */
			$el = new el($haystack);
			$children  = $el->getParent()->getChildren($tag);
			foreach($children as $key=>$node) if ($node->match($el)) { $elkey = $key; break; }
			if (!isset($elkey)) return false;
			/**
 * Separate hint. 
 *
 */
			$hint = explode('n', $match1);
			/**
 * Evaluate element key to match Nth. 
 *
 */
			eval('$num = ('.$elkey.($hint[1]!='' ? '-'.substr($hint[1], 1) : '').');');
			if ($num<0) return false;
			if ($hint[0]==0 or $num%$hint[0]==0) return true;
			else return false;
			break;
	}
}