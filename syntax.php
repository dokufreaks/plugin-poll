<?php
/**
 * Poll Plugin: allows to create simple polls
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>  
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_poll extends DokuWiki_Syntax_Plugin {
  
  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-08-19',
      'name'   => 'Poll Plugin',
      'desc'   => 'allows to create simple polls',
      'url'    => 'http://wiki.splitbrain.org/plugin:poll',
    );
  }

  function getType(){ return 'substition';}
  function getPType(){ return 'block';}
  function getSort(){ return 167; }
  
  /**
   * Connect pattern to lexer
   */
  function connectTo($mode){
    $this->Lexer->addSpecialPattern('<poll.*?>.+?</poll>', $mode, 'plugin_poll');
  }

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    $match = substr($match, 6, -7);  // strip markup
    list($title, $options) = preg_split('/>/u', $match, 2);
    if (!$options){
      $options = $title;
      $title   = NULL;
    }
    $options = explode('*', $options);
    
    $c = count($options);
    for ($i = 0; $i < $c; $i++){
      $options[$i] = trim($options[$i]);
    }
        
    return array(trim($title), $options);
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
  
    if ($mode == 'xhtml'){
      global $ID;
      
      $options = $data[1];
      $title   = $renderer->_xmlEntities($data[0]);
      
      // prevent caching to ensure the poll results are fresh
      $renderer->info['cache'] = false;
            
      // get poll file contents
      $pfile = metaFN(md5($title), '.poll');
      $poll  = unserialize(@file_get_contents($pfile));
      
      // output the poll
      $renderer->doc .= '<fieldset class="poll">'.
        '<legend>'.$title.'</legend>';
      $more = trim(array_shift($options));
      if ($more){
        $renderer->doc .= '<div>'.$renderer->_xmlEntities($more).'</div>';
      }
        
      // check if user has voted already
      $ip = clientIP(true);
      if (isset($poll['ips']) && in_array($ip, $poll['ips'])){
      
        // display results
        $renderer->doc .= $this->_pollResults($poll);

      } elseif ($vote = $_REQUEST['vote']){
      
        // user has just voted -> update results
        $c = count($options);
        for ($i = 0; $i < $c; $i++){
          $opt = $renderer->_xmlEntities($options[$i]);
          if ($vote == $opt){
            $poll['results'][$opt] += 1;
            $poll['votes'] += 1;
            $poll['ips'][] = $ip;
          } elseif (!isset($poll['results'][$opt])){
            $poll['results'][$opt] = 0;
          }
        }
        $fh = fopen($pfile, 'w');
        fwrite($fh, serialize($poll));
        fclose($fh);
        
        // display results
        $renderer->doc .= $this->_pollResults($poll);

      } elseif (count($options) > 0){
      
        // display poll form
        $renderer->doc .= $this->_pollForm($options, $renderer);

      } else {
        
        // display results
        $renderer->doc .= $this->_pollResults($poll);

      }
      $renderer->doc .= '</fieldset>';
          
      return true;
    }
    return false;
  }
  
  function _pollResults($poll){
    $total = $poll['votes'];
    if ($total == 0) return '';
  
    $ret = '<table class="blind">';
    $c = count($poll['results']);
    $options = array_keys($poll['results']);
    $votes   = array_values($poll['results']);
    for ($i = 0; $i < $c; $i++){
      $absolute = $votes[$i];
      $percent  = round(($absolute*100)/$total);
      $ret .= '<tr><td>'.$options[$i].'</td><td><div class="poll_bar">';
      if ($percent) $ret .= '<div class="poll_full" style="width:'.($percent*2).'px">&nbsp;</div>';
      $ret .= '</div></td><td class="rightalign">'.$percent.'%</td>'.
        '<td class="rightalign">('.$absolute.')</td></tr>';
    }
    $ret .= '</table>';
    
    return $ret;
  }
  
  function _pollForm($options, &$renderer){
    global $lang;
    global $ID;
    
    $i = 0;
    $ret = '<form id="poll__form" method="post" action="'.script().'" accept-charset="'.$lang['encoding'].'"><div class="no">'.
      '<input type="hidden" name="do" value="show" />'.
      '<input type="hidden" name="id" value="'.$ID.'" />';
    foreach ($options as $option){
      $i++;
      $option = $renderer->_xmlEntities($option);
      $ret.= '<label class="simple" for="poll__option'.$i.'">'.
        '<input type="radio" name="vote" id="poll__option'.$i.'" '.
        'value="'.$option.'" /> <span>'.$option.'</span></label>';
    }
    $ret .= '<input class="button" type="submit" '.
      'value="'.$this->getLang('btn_vote').'" />'.
      '</div></form>';
    
    return $ret;
  }
}
 
//Setup VIM: ex: et ts=4 enc=utf-8 :
