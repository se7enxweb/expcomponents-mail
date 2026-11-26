<?php
/**
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @version //autogentag//
 * @filesource
 * @package Mail
 * @subpackage Tests
 */

/**
 * Runs fake_smtp_server.php in a separate process.
 *
 * The process is started the way the Exponential kernel tests start their
 * loopback servers (ezpLoopbackServer): proc_open() would hand every open
 * descriptor of the test process to the server (flock()ed files, the pipe the
 * test output goes to), so each descriptor above stderr becomes /dev/null in
 * the child; a guarding shell stops the server on TERM and as soon as the
 * test process is gone, so a test process that dies before its tearDown
 * leaves no server behind; and the port line is awaited with a timeout, so a
 * server that does not start fails the test instead of hanging it.
 *
 * @package Mail
 * @subpackage Tests
 * @access private
 */
class ezcMailFakeSmtpServer
{
    /**
     * Seconds to wait for the "PORT <port>" line.
     */
    const START_TIMEOUT = 10;

    public $port;

    /**
     * The argument that names this server's process (see processIds()).
     */
    public $argument;

    private $process;

    private $pipes;

    public function __construct( array $config = array() )
    {
        $config += array( 'id' => bin2hex( random_bytes( 8 ) ) );
        $this->argument = base64_encode( json_encode( $config ) );
        $command = array( PHP_BINARY, __DIR__ . '/fake_smtp_server.php', $this->argument );
        $descriptors = array(
            0 => array( 'file', DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null', 'r' ),
            1 => array( 'pipe', 'w' ),
            2 => array( 'pipe', 'w' ),
        );
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            // No POSIX shell (Windows): the server alone
            $this->process = proc_open( self::commandLine( $command ), $descriptors, $this->pipes );
        }
        else
        {
            foreach ( (array) @scandir( '/dev/fd' ) as $fd )
            {
                if ( ctype_digit( (string) $fd ) && (int) $fd > 2 )
                {
                    $descriptors[(int) $fd] = array( 'file', '/dev/null', 'r' );
                }
            }
            // The shell gives its own stdout and stderr away once the server
            // runs, so the pipes end when the server ends
            $script = '"$@" & server=$!; exec >/dev/null 2>&1; '
                    . 'stop() { kill "$server" 2>/dev/null; wait "$server" 2>/dev/null; exit 0; }; '
                    . 'trap stop TERM INT HUP; '
                    . 'while kill -0 "$PPID" 2>/dev/null && kill -0 "$server" 2>/dev/null; do sleep 1 & wait $!; done; stop';
            // A command line, not an argument list (PHP < 7.4); exec makes the
            // guarding shell the process that proc_terminate() signals
            $this->process = proc_open( 'exec ' . self::commandLine( array_merge( array( '/bin/sh', '-c', $script, 'sh' ), $command ) ), $descriptors, $this->pipes );
        }
        if ( !is_resource( $this->process ) )
        {
            throw new RuntimeException( 'Fake SMTP server did not start: proc_open() failed' );
        }
        $line = $this->readLine( self::START_TIMEOUT );
        if ( !preg_match( '/^PORT (\d+)$/', trim( (string) $line ), $matches ) )
        {
            stream_set_blocking( $this->pipes[2], false );
            $error = (string) stream_get_contents( $this->pipes[2] );
            $this->stop();
            throw new RuntimeException( 'Fake SMTP server did not start: ' . ( $line === false ? 'no port line within ' . self::START_TIMEOUT . ' s' : $line ) . ' ' . $error );
        }
        $this->port = (int) $matches[1];
    }

    /**
     * Returns $arguments as one command line (proc_open() takes an argument
     * list only from PHP 7.4 on)
     */
    public static function commandLine( array $arguments )
    {
        return implode( ' ', array_map( 'escapeshellarg', $arguments ) );
    }

    /**
     * Returns the next line of the server's stdout, or false when none came
     * within $timeout seconds.
     */
    private function readLine( $timeout )
    {
        $read = array( $this->pipes[1] );
        $write = $except = null;
        if ( !@stream_select( $read, $write, $except, $timeout ) )
        {
            return false;
        }
        return fgets( $this->pipes[1] );
    }

    /**
     * Waits for the connection to end and returns the received lines as an
     * array of array( type, line ), where type is 'command', 'data' or
     * 'end-of-data'.
     */
    public function received()
    {
        $log = json_decode( (string) stream_get_contents( $this->pipes[1] ), true );
        $this->stop();
        return is_array( $log ) ? $log : array();
    }

    /**
     * Returns the received commands, without line endings.
     */
    public function commands()
    {
        $commands = array();
        foreach ( $this->received() as $entry )
        {
            if ( $entry[0] === 'command' )
            {
                $commands[] = rtrim( $entry[1], "\r\n" );
            }
        }
        return $commands;
    }

    /**
     * Stops the server (through its guarding shell) and waits for it.
     */
    public function stop()
    {
        if ( is_resource( $this->process ) )
        {
            foreach ( $this->pipes as $pipe )
            {
                if ( is_resource( $pipe ) )
                {
                    fclose( $pipe );
                }
            }
            proc_terminate( $this->process );
            proc_close( $this->process );
        }
    }
}
?>
