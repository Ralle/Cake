<?php

class CommandReader
{
  static public function read($arguments)
  {
    if (isset($arguments[0]))
    {
      switch ($arguments[0])
      {
        case 'init':
          try
          {
            Cake::newConfig();
          }
          catch (Exception $e)
          {
            echo $e->getMessage(), "\r\n";
          }
          break;

        case 'config':
          Cake::forceLoad();
          if (isset($arguments[1]) && isset($arguments[2]))
          {
            switch ($arguments[1])
            {
              case 'host':
              case 'user':
              case 'pass':
              case 'protocol':
              case 'path':
                Cake::setConnection($arguments[1], $arguments[2]);
                break;
            }
          }
          else
          {
            exit ("Need more arguments\r\n");
          }

          break;

        case 'ignore':
         Cake::setIgnore($arguments[1]);
         break;
        
        case 'unignore':
         Cake::delIgnore($arguments[1]);
         break;
        
        case 'debug':
         Cake::forceLoad();
         print_r(Cake::$data);
         break;
        
        case 'clean':
          Cake::cleanUp();
          break;
        
        case 'done':
         Cake::setDone();
         break;
        
        case 'n':
          Cake::upload(true);
          break;
      }
    }
    else
    {
      Cake::upload();
    }
  }
}


?>