// Enhanced speedtest with packet loss measurement
function EnhancedSpeedtest() {
    var s = new Speedtest();
    var originalOnEnd = s.onend;
    var packetLossResult = 0;
    
    // Measure packet loss
    this.measurePacketLoss = function(callback) {
        var totalPings = 20;
        var successfulPings = 0;
        var completed = 0;
        
        console.log("Measuring packet loss...");
        
        for (var i = 0; i < totalPings; i++) {
            var xhr = new XMLHttpRequest();
            xhr.timeout = 1000; // 1 second timeout
            
            xhr.onload = function() {
                successfulPings++;
                completed++;
                checkComplete();
            };
            
            xhr.onerror = xhr.ontimeout = function() {
                completed++;
                checkComplete();
            };
            
            xhr.open('GET', 'backend/empty.php?t=' + Date.now(), true);
            xhr.send();
        }
        
        function checkComplete() {
            if (completed === totalPings) {
                packetLossResult = ((totalPings - successfulPings) / totalPings * 100).toFixed(2);
                console.log("Packet loss: " + packetLossResult + "%");
                if (callback) callback(packetLossResult);
            }
        }
    };
    
    // Override telemetry to include packet loss
    s.setParameter("url_telemetry", "results/telemetry_enhanced.php");
    
    // Wrap the original onend
    s.onend = function(aborted) {
        if (!aborted) {
            // Measure packet loss after test completes
            this.measurePacketLoss(function(loss) {
                // Send enhanced telemetry
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'results/telemetry_enhanced.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                var data = 'dl=' + s.getState().dlStatus +
                          '&ul=' + s.getState().ulStatus +
                          '&ping=' + s.getState().pingStatus +
                          '&jitter=' + s.getState().jitterStatus +
                          '&packet_loss=' + loss +
                          '&latency=' + s.getState().pingStatus +
                          '&ispinfo=' + encodeURIComponent(s.getState().clientIp) +
                          '&extra=';
                
                xhr.send(data);
            });
        }
        
        if (originalOnEnd) originalOnEnd(aborted);
    };
    
    return s;
}
