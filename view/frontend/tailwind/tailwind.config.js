module.exports = {
    content: ['../templates/**/*.phtml'],
    theme: {
        extend: {
            colors: {
                'postnl': {
                    blue: {
                        darker: '#001A73',
                        DEFAULT: '#3440b6',
                        lighter: '#00A1E1'
                    },
                    orange: {
                        darker: '#ED7000',
                        DEFAULT: '#FF8D00',
                        lighter: '#FFAD00'
                    },
                    gray: {
                        darker: '#27324C',
                        'middle': '#66728A',
                        DEFAULT: '#ADB5C5',
                        lighter: '#D4D9E3',
                        light: '#F3F4F7'
                    },
                    green: {
                        DEFAULT: '#43B02A'
                    }
                }
            },
        }
    }
};
