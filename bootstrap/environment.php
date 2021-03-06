<?php

use Dotenv\Dotenv;

/*
|--------------------------------------------------------------------------
| Detect The Application Environment
|--------------------------------------------------------------------------
|
| Laravel takes a dead simple approach to your application environments
| so you can just specify a machine name for the host that matches a
| given environment, then we will automatically detect it for you.
|
*/
$env = $app->detectEnvironment(function(){

    $nlxcms_file = base_path('/nlxcms.json');
    $env_file_name = null;

    if(file_exists($nlxcms_file))
    {

        $nlxcms = file_get_contents(base_path('/nlxcms.json'));
        $nlxcms = json_decode($nlxcms, true);

        if(isset($nlxcms['environments']) && isset($nlxcms['environments']['default']))
        {
            $env_file_name = $nlxcms['environments']['default']['env_file'];
        }

        $host = null;
        $actual_url = null;
        $is_sub_domain = null;
        $sub_domain = null;
        $env_sub_domain = null;
        $env_root_domain = null;
        $root_domain = null;

        if(isset($_SERVER) && isset($_SERVER['HTTP_HOST']))
        {
            $actual_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            $url_arr = explode('.', $actual_url);


            // Get Root Domain
            $pattern = "((?<=\.)[a-z0-9]*(?=\.)(?=[a-z]*))";
            preg_match($pattern, $_SERVER["HTTP_HOST"], $matches);
            $parts = explode('.', $_SERVER["HTTP_HOST"]);
            $tld = (sizeof($parts) ? ('.' . end($parts)) : false);

            if(isset($matches[0]))
            {
                $root_domain = $matches[0].$tld;
            }

            // Get Sub domain
            if(count($url_arr) > 2 )
            {
                $is_sub_domain = true;

                $pattern = "((?<=:\/\/)[a-z0-9]*(?=\.))";
                preg_match($pattern, $actual_url, $matches);

                if(isset($matches[0]))
                {
                    $sub_domain =  $matches[0];
                }

            }
        }



        if($actual_url && is_null($is_sub_domain))
        {
            if(isset($nlxcms['environments'])
                && is_array($nlxcms['environments'])
                && count($nlxcms['environments'])>0
            )
            {
                $actual_url = explode( '://', $actual_url);
                foreach ($nlxcms['environments'] as $key => $environment)
                {
                    $environment_app_url = explode( '://', $environment['app_url']);

                    if ( isset($environment_app_url[1])
                        && isset($actual_url[1])
                        && strpos(strval($actual_url[1]), strval($environment_app_url[1])) !== false){
                        $env_file_name = $environment['env_file'];
                    }
                }

            }
        } else if(!is_null($is_sub_domain))
        {

            foreach ($nlxcms['environments'] as $key => $environment)
            {
                $environment_app_url = explode( '.', $environment['app_url']);
                $environment_app_d = explode("://", $environment_app_url[0]);

                if (
                    isset($environment_app_d[1])
                    && isset($sub_domain)
                ){

                    $pattern = "((?<=:\/\/)[a-z0-9]*(?=\.))";
                    preg_match($pattern, $environment['app_url'], $matches);

                    if(isset($matches[0]))
                    {
                        $env_sub_domain =  $matches[0];
                    }


                    if(!$env_sub_domain)
                    {
                        continue;
                    }
                    //Stared sub domain
                    if($environment_app_d['1'] == '*' && $sub_domain == $env_sub_domain)
                    {
                        $sub_domain_arr = explode('.', $sub_domain);

                        unset($sub_domain_arr[0]);
                        unset($environment_app_url[0]);

                        $sub_domain_derived = implode('.', $sub_domain_arr);
                        $environment_app_url = implode('.', $sub_domain_arr);

                        if($sub_domain_derived == $environment_app_url)
                        {
                            $env_file_name = $environment['env_file'];
                        }

                    } else{
                        $actual_d = explode( '://', $actual_url);
                        $environment_app_url = explode( '://', $environment['app_url']);
                        if (strpos($actual_d[1], $environment_app_url[1]) !== false){
                            $env_file_name = $environment['env_file'];
                        }
                    }

                }
            }
        }

    }


    if(!$env_file_name)
    {
        $env_file_name = '.env';
    }

    $env_file_path = __DIR__.'/../'.$env_file_name;

    if(!file_exists($env_file_path))
    {
        header("HTTP/1.1 500 Internal Server Error", true, 500);
        echo $env_file_name.' file does not exist.';//html for 500 page
        exit(1);
    }


    putenv("ENV_FILE=".$env_file_name);

    $dotenv = Dotenv::createImmutable(__DIR__.'/../', $env_file_name);
    $dotenv->load();

});
