document.addEventListener('DOMContentLoaded', function() {
    // Initialize all charts on the page
    const chartContainers = document.querySelectorAll('[data-chart]');
    
    chartContainers.forEach(container => {
        const ctx = container.getContext('2d');
        const chartType = container.getAttribute('data-chart-type') || 'bar';
        const chartData = JSON.parse(container.getAttribute('data-chart-data'));
        
        new Chart(ctx, {
            type: chartType,
            data: chartData,
            options: getChartOptions(chartType)
        });
    });

    // Chart options based on type
    function getChartOptions(type) {
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== undefined) {
                                label += context.parsed.y;
                            } else {
                                label += context.parsed;
                            }
                            return label;
                        }
                    }
                }
            }
        };

        switch(type) {
            case 'pie':
            case 'doughnut':
                return {
                    ...baseOptions,
                    plugins: {
                        ...baseOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                };
            
            case 'bar':
                return {
                    ...baseOptions,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                };
            
            case 'line':
                return {
                    ...baseOptions,
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                };
            
            default:
                return baseOptions;
        }
    }

    // Time allocation chart for study plans
    window.renderTimeAllocationChart = function(data, canvasId) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        // Process data - group by subject
        const subjectHours = {};
        data.forEach(item => {
            if (!subjectHours[item.subject]) {
                subjectHours[item.subject] = 0;
            }
            
            // Convert "3 hrs 22 mins" to decimal hours
            const hoursMatch = item.hours_allocated.match(/(\d+) hrs/);
            const minsMatch = item.hours_allocated.match(/(\d+) mins/);
            const hours = hoursMatch ? parseFloat(hoursMatch[1]) : 0;
            const mins = minsMatch ? parseFloat(minsMatch[1]) / 60 : 0;
            subjectHours[item.subject] += hours + mins;
        });
        
        // Sort subjects by hours (descending)
        const sortedSubjects = Object.keys(subjectHours).sort((a, b) => subjectHours[b] - subjectHours[a]);
        
        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels: sortedSubjects,
                datasets: [{
                    data: sortedSubjects.map(subject => subjectHours[subject]),
                    backgroundColor: [
                        '#4a6cf7', '#6e8efb', '#8da6fb', '#adc2fc', '#cbd9fd',
                        '#a777e3', '#ba8eec', '#cca5f5', '#ddbdf7', '#eed4fa'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Study Time Allocation by Subject',
                        font: { size: 18 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value.toFixed(1)} hrs (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    };

    // Difficulty distribution chart
    window.renderDifficultyChart = function(data, canvasId) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        // Count topics by difficulty
        const difficultyCounts = Array(10).fill(0);
        data.forEach(item => {
            const difficulty = parseInt(item.difficulty) || 1;
            difficultyCounts[difficulty - 1]++;
        });
        
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 10}, (_, i) => i + 1),
                datasets: [{
                    label: 'Number of Topics',
                    data: difficultyCounts,
                    backgroundColor: '#4a6cf7',
                    borderColor: '#3a5bd9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Topic Difficulty Distribution',
                        font: { size: 18 }
                    },
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Topics' }
                    },
                    x: {
                        title: { display: true, text: 'Difficulty Level (1-10)' }
                    }
                }
            }
        });
    };

    // Completion status chart
    window.renderCompletionChart = function(data, canvasId) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const completed = data.filter(item => item.is_completed == 1).length;
        const pending = data.length - completed;
        
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending'],
                datasets: [{
                    data: [completed, pending],
                    backgroundColor: ['#2ecc71', '#e74c3c'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Completion Status',
                        font: { size: 18 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    };

    // Download chart as image
    window.downloadChart = function(chart, filename) {
        const link = document.createElement('a');
        link.download = filename || 'chart.png';
        link.href = chart.toBase64Image();
        link.click();
    };
});