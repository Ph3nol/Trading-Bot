FROM node:16.1.0-alpine3.12 as ui-builder

RUN mkdir /app

RUN wget --quiet https://github.com/freqtrade/frequi/archive/refs/tags/0.0.6.tar.gz -O /tmp/ui.tar.gz \
    && tar xf /tmp/ui.tar.gz -C /app --strip 1 \
    && rm /tmp/ui.tar.gz

WORKDIR /app

# ENV PATH /usr/src/app/node_modules/.bin:$PATH

#COPY package.json /app/package.json
#COPY yarn.lock /app/yarn.lock

RUN apk add --update --no-cache python3 g++ make\
    && yarn \
    && apk del python3 g++ make

RUN yarn global add @vue/cli

COPY . /app
RUN yarn build

FROM nginx:1.19.10-alpine
COPY  --from=ui-builder /app/dist /etc/nginx/html
COPY  --from=ui-builder /app/nginx.conf /etc/nginx/nginx.conf
EXPOSE 80
CMD ["nginx"]