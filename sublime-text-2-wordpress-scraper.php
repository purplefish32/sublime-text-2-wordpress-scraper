<?php
function tokenizeme() {

    // get each entry
   $stack[] = rtrim(ABSPATH, "/");
   while ($stack) {
       $thisdir = array_pop($stack);
       if ($dircont = scandir($thisdir)) {
           $i=0;
           while (isset($dircont[$i])) {
               if ($dircont[$i] !== '.' && $dircont[$i] !== '..' && $dircont[$i] != "wp-content" ) {
                   $current_file = "{$thisdir}/{$dircont[$i]}";
                   if (is_file($current_file) &&strpos($dircont[$i], '.php') ) {
                       $path[] = "{$thisdir}/{$dircont[$i]}";
                   } elseif (is_dir($current_file) && $dircont[$i] != ".svn" ) {
                        $path[] = "{$thisdir}/{$dircont[$i]}";
                       $stack[] = $current_file;
                   }
               }
               $i++;
           }
       }
   }
   // echo '<pre>';
   $debug = false;
   $in_function = false;
   $in_class = false;
   $in_function_params = false;
   $function_params = '';
   foreach ( $path as $path_key => $file ) :
        if ( ! is_file($file) )
            continue;
        $source = file_get_contents($file);
        $tokens = token_get_all($source);
        $parenthesis_depth = 0;
        $braces_depth = 0;
        $in_function_params = false;

        foreach($tokens as $key => $token) :
            if ( is_array($token) ) {
                if($debug):
                    if(token_name($token[0]) != "T_WHITESPACE")
                        echo token_name($token[0]) . ' ' . (($in_class)?'yes':'no') . '<BR>';
                endif;
                //switch the token name
                switch( token_name($token[0]) ) {
                    case 'T_CURLY_OPEN' :
                        $braces_depth++;
                    break;
                    case 'T_CLASS' :
                        $in_class = true;
                        $brace_depth = 0;
                    break;
                    case 'T_FUNCTION' :
                        $in_function = true;
                        $got_function_name = false;
                    break;
                    case 'T_STRING' :
                        if ( $in_function && !$got_function_name ) :
                            $current_function = $token[1];
                            $got_function_name = true;
                            $in_function_params = true;
                            continue 2;
                        endif;
                    case '' :
                    break;
                }
                if( $in_function_params ) {
                    $function_params .= $token[1];
                }
            } else {
                if( $in_function_params ) {
                    if($debug) echo $token . '<BR>';
                    switch($token) {
                        case '(' :
                            $parenthesis_depth++;
                            continue 2;
                        break;
                        case ')' :
                        if($parenthesis_depth)
                                $parenthesis_depth--;
                            if($parenthesis_depth)
                                continue 2;
                            $functions[] = array($current_function, trim($function_params), $in_class, $file );
                            if($debug) var_dump(array($current_function, trim($function_params), $in_class, $file ) );
                            $current_function = NULL;
                            $function_params = NULL;
                            $in_function_params = false;
                            continue 2;
                        break;
                    }
                    $function_params .= $token;
                } else {
                    switch($token) {
                    case '{' :
                        $braces_depth++;
                        continue 2;
                    case '}' :
                        $braces_depth--;
                        if(!$braces_depth)
                            $in_class = false;
                        continue 2;
                    }
                }
            }

        endforeach;
        $tokens = NULL;

    endforeach;

    foreach ( $functions as $key => $function ) {
        if($function[2]) {
            unset($functions[$key]);
            continue;
        }
        $args = array();
        if(!empty($function[1])) :
            $args = split(',', $function[1]);
            foreach($args as $key => $arg) {
                $new_arg_text = trim($arg, "\n\r\t ");
                $new_arg_text = str_replace('"', "'", $new_arg_text);
                $new_arg_text = str_replace('$', '\\\$', $new_arg_text);
                $args[$key] = '${' . ($key+1) . ':' .  $new_arg_text . '}';
            }
        endif;
        ?>{"trigger": "<?php echo $function[0]; ?>", "contents": "<?php echo $function[0]; ?>(<?php echo implode(',', $args); ?>)" },
<?php
    }
    die;
} 
