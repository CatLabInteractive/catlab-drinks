<template>
    <a href="#" @click.prevent="logout" class="nav-link">Logout</a>
</template>
<script>
    export default {
        methods:{
            logout:function(){
                axios.post('/logout').then(response => {
                    if (response.status === 302 || 401) {
                        window.location.reload();
                    }
                    else {
                        // throw error and go to catch block
                        console.error(response);
                    }
                }).catch(error => {
                    console.error(error);
                });
            },
        },
    }
</script>