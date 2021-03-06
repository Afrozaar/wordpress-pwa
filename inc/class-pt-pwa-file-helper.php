<?php

    class PtPwaFileHelper {

        /**
         * @param $path
         * @param $contents
         * @return bool
         */
        public function write_file($path, $contents) {
            if (file_exists($path)) {
                unlink($path);
            }

            $file = fopen($path, "w") or die("Unable to open file!");
            fwrite($file, $contents);
            fclose($file);
            return true;
        }

        /**
         * @param $path
         * @return bool|string
         */
        public function read_file($path) {
            if (file_exists($path)) {
                $file = fopen($path, "r") or die("Unable to open file!");
                $contents = fread($file, filesize($path));
                fclose($file);
                return $contents;
            }
            return false;
        }
    }
