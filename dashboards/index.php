<?php
session_start();

$dsn        = 'pgsql:dbname=sample_data;host=0.0.0.0';
$user       = 'user';
$password   = 'password';
$dbh        = new PDO($dsn, $user, $password);
$submit     = $_POST['submit'] ?? false;
$reports    = $dbh->query('SELECT * FROM report ORDER BY id ASC;');

if($submit) {
    $dashboardReports = $_POST['dashboardReports'] ?? false;

    if($dashboardReports) {
        $reportCount = count($dashboardReports);
        $fullChartCards = floor($reportCount/3);

        if($reportCount%3 > 0) {
            $numChartCards = $fullChartCards + 1;
        }
        else {
            $numChartCards = $fullChartCards;
        }

        if($numChartCards == 1) {
            $canvasHeight = 50;
        }
        else {
            $canvasHeight = 90;
        }

        switch ($numChartCards) {
            case 1:
                $chartCardHeight = floor(100/$numChartCards);
                break;            
            case 2:
                $chartCardHeight = floor(100/$numChartCards);
                break;
            case 3:
                $chartCardHeight = floor(200/$numChartCards);
                break;
            case 4:
                $chartCardHeight = floor(200/$numChartCards);
                break;
        }

        $chartNum=0;
        $chartComponents=[];
        $canvasElements=[];
        $chartConsts=[];
        $newCharts=[];

        foreach ($dashboardReports as $report) {
            $chartMetaData = $dbh->query("SELECT name, sql, chart_type FROM report WHERE id=$report;")->fetch();
            $chartConfig = generateChartConfig($chartMetaData, $dbh);
            $chartComponents[] = generateChartComponents($chartConfig, $chartNum, $canvasHeight);
            $chartNum++;
        }

        foreach($chartComponents as $component) {
            $canvasElements[] = $component[0];
            $chartConsts[] = $component[1];
            $newCharts[] = $component[2];
        }

        $finish = generateCharts($canvasElements, $chartConsts, $newCharts, $chartCardHeight, $reportCount);
        echo '
            <head>
                <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <title>Dashboards</title>
                <meta name="description" content="">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <link rel="stylesheet" href="./style.css">
            </head>';
        echo $finish;
    } else {
        echo "You must select at least one report.";
    }
} elseif ($submit == false) {
    echo '
        <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <title>Report Select</title>
            <meta name="description" content="">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
            <link rel="stylesheet" href="./style.css">
        </head>
        <body class="d-flex justify-content-center text-center color4" style="flex-direction:column; height:100%;">
            <form action="/dashboards/index.php" method="POST" class="d-flex justify-content-center text-center color4" style="flex-direction:column; width:100%; height:75%;">
                <script src="" async defer></script>
                <div class="container">
                    <div class="jumbotron">
                        <h1 style="color: white;">Report Select</h1>      
                    </div>  
                </div>
                <div class="card text-center mb-3 color4 border border-0" style="width:40%;height:40%;margin: 0 auto;">
                    <div class="card-body">
                        <select name="dashboardReports[]" class="form-select" id="dashboardReports" multiple size="5" style="height:100%;width:100%;color:#e8e6e3;background-color:#181a1b;border:#383d3f;">';
    foreach ($reports as $report) {
        echo "<option value=" . $report['id'] . ">" . $report['name'] . "</option>";
    }
        echo '          </select>
                    </div>
                    <br>
                    <input type="submit" name="submit" id="submit" class="center btn btn-dark">
                </div>
            </form>
        </body>';
}

function generateChartConfig($chartMetaData, $db_conn) {
    $chartName = $chartMetaData[0];
    $chartSql = $chartMetaData[1];
    $chartType = $chartMetaData[2];
    //Get Data labels and dataset.
    $chartRows = transpose($db_conn->query($chartSql)->fetchAll(PDO::FETCH_ASSOC));
    $chartRowsCategories = array_keys($chartRows);
    //Use a standard for loop with i=0 being the labels, and each i after that being a dataset.
    for($i=0; $i<count($chartRowsCategories); $i++) {
        if($i == 0) {
            $chartLabels = $chartRows[$chartRowsCategories[$i]];
        } else {
            $chartDataSet = $chartRows[$chartRowsCategories[$i]];
        }
    }

    $chart = new stdClass();
    switch ($chartType) {
        case 'bar':
            $chart = generateChartObj($chart,$chartType,$chartLabels,$chartName,$chartDataSet);
            //$chart = addChartAxes($chartLabels[0], $chartLabels[1], $chart);
            break;            
        case 'doughnut':
            $chart = generateChartObj($chart,$chartType,$chartLabels,$chartName,$chartDataSet);
            break;
        case 'pie':
            $chart = generateChartObj($chart,$chartType,$chartLabels,$chartName,$chartDataSet);
            break;
        case 'line':
            $chart = generateChartObj($chart,$chartType,$chartLabels,$chartName,$chartDataSet);
            //$chart = addChartAxes($chartLabels[0], $chartLabels[1], $chart);
            break;
    }
    return $chart;
}

function generateChartObj($chart,$chartType,$chartLabels,$chartName,$chartDataSet) {
    $chart->type = $chartType;
    //Chart Data object
    $chart->data = new stdClass();
    $chart->data->labels = $chartLabels;
    $chart->data->datasets = [new stdClass];
    $chart->data->datasets[0]->label = $chartName;
    $chart->data->datasets[0]->data = $chartDataSet;
    //Chart Options Object
    $chart->options = new stdClass();
    $chart->options->scales = new stdClass();
    $chart->options->aspectRatio = 1;
    return $chart;
}

function generateChartComponents($chartConfig, $chartNum, $canvasHeight) {
    $chartConfig = json_encode($chartConfig);
    $canvasElement = "<div class=\"chartBox\" style=\"height:$canvasHeight%;\"><canvas id=\"chart$chartNum\" class=\"innerCanvas\"></canvas></div>";
    $chartConst = "const chart$chartNum = document.getElementById('chart$chartNum');";
    $newChart = "new Chart(chart$chartNum, $chartConfig);";
    return [$canvasElement, $chartConst, $newChart];
}

function generateCharts($canvasElements, $chartConsts, $newCharts, $chartCardHeight, $reportCount) {
    $i = 0;
    $chartString = "<div id=\"chartCard\" class=\"chartCard\" style=\"height:$chartCardHeight%;\">";
    foreach($canvasElements as $canvasElement) {
        $chartString .= $canvasElement;
        $i++;
        if($i == $reportCount) {
            $chartString .= "</div>";
        } elseif(is_int($i/3) && $i !== 0) {
            $chartString .= "</div><div id=\"chartCard\" class=\"chartCard\" style=\"height:$chartCardHeight%;\">";
        }
    }

    $chartString .= "</div>
    <script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>
    <script>";
    foreach($chartConsts as $chartConst) {
        $chartString .= $chartConst;
    }

    foreach($newCharts as $newChart) {
        $chartString .= $newChart;
    }
    $chartString .= "
    </script>";

    return $chartString;
}

function addChartAxes($xAxis, $yAxis, $chart) {
    $chart->options->scales->x = new stdClass();
    $chart->options->scales->x->title = new stdClass();
    $chart->options->scales->x->title->display = true;
    $chart->options->scales->x->title->text = $xAxis;
    $chart->options->scales->y = new stdClass();
    $chart->options->scales->y->title = new stdClass();
    $chart->options->scales->y->title->display = true;
    $chart->options->scales->y->title->text = $yAxis;
    return $chart;
}

function transpose($arr) {
    $out = array();
    foreach ($arr as $key => $subarr) {
        foreach ($subarr as $subkey => $subvalue) {
            $out[$subkey][$key] = $subvalue;
        }
    }
    return $out;
}
?>