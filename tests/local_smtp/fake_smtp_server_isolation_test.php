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

require_once( dirname( __FILE__ ) . '/fake_smtp_test_case.php' );

/**
 * The fake SMTP server holds none of the test process's descriptors and does
 * not outlive the test process, not even when that is killed before its
 * tearDown (the guard the kernel tests' ezpLoopbackServer uses).
 *
 * @package Mail
 * @subpackage Tests
 */
class ezcMailFakeSmtpServerIsolationTest extends ezcTestCase
{
    private $arguments = array();

    protected function setUp(): void
    {
        if ( DIRECTORY_SEPARATOR !== '/' || !is_dir( '/proc/self' ) || !function_exists( 'posix_kill' ) )
        {
            $this->markTestSkipped( 'Needs a POSIX shell, /proc and posix_kill.' );
        }
    }

    protected function tearDown(): void
    {
        foreach ( $this->arguments as $argument )
        {
            foreach ( self::processIds( $argument ) as $pid )
            {
                @posix_kill( $pid, 9 );
            }
        }
    }

    public function testServerHoldsNoDescriptorOfTheTestProcess()
    {
        $held = tempnam( sys_get_temp_dir(), 'ezcmail_held_' );
        $handle = fopen( $held, 'r' );
        flock( $handle, LOCK_SH );
        $server = new ezcMailFakeSmtpServer();
        $this->arguments[] = $server->argument;
        try
        {
            $pids = self::processIds( $server->argument );
            $this->assertNotEmpty( $pids, 'the server runs' );
            foreach ( $pids as $pid )
            {
                foreach ( (array) @scandir( "/proc/{$pid}/fd" ) as $fd )
                {
                    $this->assertNotSame( realpath( $held ), @readlink( "/proc/{$pid}/fd/{$fd}" ), "process {$pid} holds a file of the test process" );
                }
            }
        }
        finally
        {
            $server->stop();
            fclose( $handle );
            unlink( $held );
        }
        $this->assertTrue( self::gone( $server->argument ), 'the server ends with stop()' );
    }

    public function testServerEndsWhenTheTestProcessIsKilled()
    {
        // A stand-in for a test process that dies without its tearDown
        $code = 'require ' . var_export( __DIR__ . '/fake_smtp_server_process.php', true ) . ';'
              . '$s = new ezcMailFakeSmtpServer(); echo $s->argument, "\n"; sleep( 60 );';
        $child = proc_open( array( PHP_BINARY, '-r', $code ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes );
        $argument = trim( (string) fgets( $pipes[1] ) );
        $this->arguments[] = $argument;
        $this->assertNotSame( '', $argument, 'the stand-in started a server' );
        $this->assertNotEmpty( self::processIds( $argument ), 'the server runs' );

        posix_kill( proc_get_status( $child )['pid'], 9 );
        fclose( $pipes[1] );
        proc_close( $child );

        $this->assertTrue( self::gone( $argument ), 'the server ends once the process that started it is gone' );
    }

    /**
     * Returns the ids of the processes whose command line holds $argument
     * (the server, not its guarding shell, which holds it too).
     */
    private static function processIds( $argument )
    {
        $pids = array();
        foreach ( (array) glob( '/proc/[0-9]*/cmdline' ) as $file )
        {
            $parts = explode( "\0", (string) @file_get_contents( $file ) );
            if ( in_array( $argument, $parts, true ) )
            {
                $pids[] = (int) basename( dirname( $file ) );
            }
        }
        return $pids;
    }

    private static function gone( $argument )
    {
        for ( $i = 0; $i < 50; $i++ )
        {
            if ( !self::processIds( $argument ) )
            {
                return true;
            }
            usleep( 100000 );
        }
        return false;
    }
}
?>
