<template>
    <a href="#" @click.prevent="logout" class="nav-link">Logout</a>
</template>
<script>
    export default {
        methods:{
            logout:function(){
                axios.post('/logout').then(response => {
                    if (response.status === 302 || 401) {
                        console.log('logout')
                    }
                    else {
                        // throw error and go to catch block
                    }
                }).catch(error => {

                });
            },
        },
    }
</script>