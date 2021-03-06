<?php

use Workerman\Worker;
use function Amp\delay;
use function Amp\asyncCall;
use Wind\Beanstalk\BeanstalkClient;

require __DIR__.'/common.php';

$worker = new Worker();
$worker->reusePort = false;
$worker->onWorkerStart = function() {
    asyncCall(function() {
        $client = new BeanstalkClient('192.168.4.2', 11300, [
            'autoReconnect' => true,
            'reconnectDelay' => 5,
            'concurrent' => true
        ]);
        $client->debug = true;

        try {
            yield $client->connect();
            echo "Producer connect success.\n";

            yield $client->useTube('test');

            for ($i=0; $i<100; $i++) {
                yield delay(1000);
                echo "--producer ";
                $id = yield $client->put("Hello World");
                echo $id."--\n";
            }

            echo "Put finished.\n";
        } catch (\Throwable $e) {
            echo dumpException($e);
        }
    });

    asyncCall(function() {
        $client = new BeanstalkClient('192.168.4.2', 11300, [
            'autoReconnect' => true,
            'reconnectDelay' => 5,
            'concurrent' => true
        ]);
        $client->debug = true;

        // delay(2000)->onResolve(function() use ($client) {
        //     echo "Close..\n";
        //     $client->close();
        // });

        try {
            echo "Start connect\n";
            yield $client->connect();
            echo "Connect success.\n";

            echo "Start watch\n";
            // yield $client->watch('test');
            $client->watch('test')->onResolve(function($e, $v) {
                if ($e) {
                    echo "WatchError: ".$e->getMessage()."\n";
                } else {
                    echo "Watched\n";
                }
            });

            echo "Start ignore\n";
            // yield $client->ignore('default');
            $client->ignore('default')->onResolve(function($e, $v) {
                if ($e) {
                    echo "IgnoreError: ".$e->getMessage()."\n";
                } else {
                    echo "Ignored\n";
                }
            });

            while ($data = yield $client->reserve()) {
                print_r($data);
                yield $client->delete($data['id']);
                yield delay(10);
            }
        } catch (\Exception $e) {
            echo dumpException($e);
        }
    });
};

Worker::runAll();