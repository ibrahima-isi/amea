(() => {
    const dashboard = document.querySelector('.staff-dashboard');
    if (!dashboard || !window.Chart) return;

    const plots = [...dashboard.querySelectorAll('[data-dashboard-chart]')].map(canvas => ({
        canvas,
        type: canvas.dataset.dashboardChart,
        labels: JSON.parse(canvas.dataset.labels),
        values: JSON.parse(canvas.dataset.values).map(Number),
        colors: JSON.parse(canvas.dataset.colors || '["blue"]'),
    }));
    if (!plots.length) return;

    const render = () => {
        const style = getComputedStyle(dashboard);
        const color = name => style.getPropertyValue(name).trim();

        for (const plot of plots) {
            const {canvas, type, labels, values, colors} = plot;
            const palette = colors.map(name => color('--chart-' + name));
            const options = {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                color: color('--admin-ink'),
                plugins: {legend: {display: false}},
            };
            let data = {labels, datasets: [{data: values, backgroundColor: palette}]};

            if (type === 'schools') {
                data.datasets[0].maxBarThickness = 48;
                options.scales = {
                    x: {
                        grid: {display: false},
                        ticks: {
                            color: color('--admin-muted'),
                            maxRotation: 0,
                            autoSkip: false,
                            callback(value) {
                                const label = Array.from(this.getLabelForValue(value));
                                const maxLength = Math.max(4, Math.floor(this.width / labels.length / 8));
                                return label.length > maxLength ? label.slice(0, maxLength - 1).join('') + '…' : label.join('');
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {color: color('--admin-line')},
                        ticks: {color: color('--admin-muted'), precision: 0},
                    },
                };
            }

            if (type === 'status') {
                data.datasets[0].borderColor = color('--admin-surface');
            }

            if (type === 'gender') {
                data = {
                    labels: [''],
                    datasets: labels.map((label, index) => ({
                        label,
                        data: [values[index]],
                        backgroundColor: palette[index],
                    })),
                };
                options.indexAxis = 'y';
                options.scales = {
                    x: {stacked: true, display: false, max: values[0] + values[1]},
                    y: {stacked: true, display: false},
                };
            }

            Chart.getChart(canvas)?.destroy();
            canvas.parentElement.hidden = false;
            new Chart(canvas, {type: type === 'status' ? 'pie' : 'bar', data, options});
        }
    };

    render();
    new MutationObserver(render).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme'],
    });
})();
